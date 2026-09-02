<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Points\Services\PointsService;
use App\Modules\Settings\Http\Requests\UpdatePointsSettingRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PointsSettingController extends Controller
{
    public function __construct(private readonly PointsService $points)
    {
    }

    public function edit(Request $request): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        return Inertia::render('modules/settings/Points', [
            'settings' => $this->settingsData(),
        ]);
    }

    public function update(UpdatePointsSettingRequest $request): RedirectResponse
    {
        $this->points->settings()->update($request->validated());

        return to_route('points-settings.edit')->with('success', 'Points settings updated successfully.');
    }

    /** @return array{completed_order_points: int, on_time_payment_points: int, peso_per_point: string} */
    private function settingsData(): array
    {
        $settings = $this->points->settings();

        return [
            'completed_order_points' => $settings->completed_order_points,
            'on_time_payment_points' => $settings->on_time_payment_points,
            'peso_per_point' => $settings->peso_per_point,
        ];
    }
}
