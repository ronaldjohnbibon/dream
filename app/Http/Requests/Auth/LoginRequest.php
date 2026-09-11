<?php

namespace App\Http\Requests\Auth;

use App\Modules\Logs\Services\SystemLogger;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email'    => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials.
     *
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        if (! Auth::attempt($this->only('email', 'password'), $this->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey());

            $systemLogs = app(SystemLogger::class);
            $systemLogs->record(
                type: 'security',
                action: 'login_failed',
                description: 'Login attempt failed.',
                module: 'authentication',
                status: 'failed',
                metadata: ['reason' => 'invalid_credentials'],
            );

            if (RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
                $systemLogs->record(
                    type: 'security',
                    action: 'login_blocked',
                    description: 'Login attempts were rate limited.',
                    module: 'authentication',
                    status: 'blocked',
                    metadata: ['reason' => 'rate_limited'],
                );
            }

            throw ValidationException::withMessages([
                'email' => \trans('auth.failed'),
            ]);
        }

        $user = Auth::user();

        if (! $user->is_admin && $user->account_status === 'suspended') {
            app(SystemLogger::class)->record(
                type: 'security',
                action: 'login_blocked',
                description: 'Suspended account sign-in was blocked.',
                module: 'authentication',
                recordId: (int) $user->id,
                status: 'blocked',
                metadata: ['reason' => 'account_suspended'],
            );

            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Your account has been suspended.',
            ]);
        }

        RateLimiter::clear($this->throttleKey());
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        \event(new Lockout($this));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        throw ValidationException::withMessages([
            'email' => \trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => \ceil($seconds / 60),
            ]),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->string('email')).'|'.$this->ip());
    }
}
