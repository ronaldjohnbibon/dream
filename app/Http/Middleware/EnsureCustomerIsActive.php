<?php

namespace App\Http\Middleware;

use App\Modules\Logs\Services\SystemLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCustomerIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_admin && $user->account_status === 'suspended') {
            app(SystemLogger::class)->record(
                type: 'security',
                action: 'account_access_blocked',
                description: 'Suspended account access was blocked.',
                module: 'authentication',
                recordId: (int) $user->id,
                status: 'blocked',
                metadata: ['reason' => 'account_suspended'],
            );

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return to_route('login')->withErrors([
                'email' => 'Your account has been suspended.',
            ]);
        }

        return $next($request);
    }
}
