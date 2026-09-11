<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Modules\Logs\Services\SystemLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function __construct(private readonly SystemLogger $systemLogs) {}

    /**
     * Show the login page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status'           => $request->session()->get('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        $this->systemLogs->record(
            type: 'security',
            action: 'login_succeeded',
            description: 'User signed in.',
            module: 'authentication',
            recordId: (int) $request->user()->id,
            status: 'succeeded',
            metadata: ['auth_guard' => 'web'],
        );

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $this->systemLogs->record(
            type: 'security',
            action: 'logout',
            description: 'User signed out.',
            module: 'authentication',
            recordId: (int) $request->user()->id,
            status: 'completed',
            metadata: ['auth_guard' => 'web'],
        );

        $endpoint = $request->input('push_endpoint', $request->session()->get('push_endpoint'));
        if (is_string($endpoint) && strlen($endpoint) <= 1024) {
            $request->user()->deletePushSubscription($endpoint);
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
