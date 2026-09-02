<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Settings\Http\Requests\UpdateGcashSettingRequest;
use App\Modules\Settings\Models\GcashSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class GcashSettingController extends Controller
{
    public function edit(): Response
    {
        abort_unless(request()->user()?->is_admin, 403);

        $setting = GcashSetting::query()->find(1);

        return Inertia::render('modules/settings/Gcash', [
            'qrCodeUrl' => $setting?->qr_code_path ? Storage::disk('public')->url($setting->qr_code_path) : null,
        ]);
    }

    public function update(UpdateGcashSettingRequest $request): RedirectResponse
    {
        $setting = GcashSetting::query()->findOrNew(1);
        $setting->id = 1;
        $previousPath = $setting->qr_code_path;
        $path = $request->file('qr_code')->store('gcash-qr', 'public');

        $setting->update(['qr_code_path' => $path]);

        if ($previousPath) {
            Storage::disk('public')->delete($previousPath);
        }

        return back()->with('success', 'GCash QR code updated successfully.');
    }
}
