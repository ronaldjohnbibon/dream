<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Logs\Services\SystemLogger;
use App\Modules\Settings\Http\Requests\UpdatePasswordRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('modules/settings/Password');
    }

    public function update(UpdatePasswordRequest $request, SystemLogger $systemLogs): RedirectResponse
    {
        $request->user()->update([
            'password' => Hash::make($request->validated('password')),
        ]);

        $systemLogs->record(
            type: 'security',
            action: 'password_changed',
            description: 'Password changed.',
            module: 'authentication',
            recordId: (int) $request->user()->id,
            status: 'completed',
            metadata: ['affected_user_id' => $request->user()->id],
        );

        return back()->with('success', 'Password updated successfully.');
    }
}
