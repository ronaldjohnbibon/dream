<?php

namespace App\Modules\Settings\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Delivery\Models\DeliveryArea;
use App\Modules\Logs\Services\ActivityLogger;
use App\Modules\Points\Services\PointsService;
use App\Modules\Settings\Http\Requests\UpdateSystemSettingRequest;
use App\Modules\Settings\Models\GcashSetting;
use App\Modules\Settings\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class SystemSettingController extends Controller
{
    public function __construct(private readonly PointsService $points, private readonly ActivityLogger $activityLogs)
    {
    }

    public function edit(Request $request): Response
    {
        abort_unless($request->user()?->is_admin, 403);

        $system = SystemSetting::current();
        $gcash = GcashSetting::query()->find(1);
        $points = $this->points->settings();
        $deliveryAreas = DeliveryArea::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'delivery_fee']);
        $freeDeliveryAreaIds = $deliveryAreas->pluck('id')->intersect($system->free_delivery_area_ids ?? [])->values()->all();

        return Inertia::render('modules/settings/System', [
            'settings' => [
                'business_name' => $system->business_name,
                'logo_url' => $system->logo_path ? Storage::disk('public')->url($system->logo_path) : null,
                'contact_number' => $system->contact_number,
                'address' => $system->address,
                'gcash_account_name' => $gcash?->account_name,
                'gcash_account_number' => $gcash?->account_number,
                'gcash_qr_code_url' => $gcash?->qr_code_path ? Storage::disk('public')->url($gcash->qr_code_path) : null,
                'pautang_enabled' => $system->pautang_enabled,
                'pautang_installments' => $system->pautang_installments,
                'pautang_payment_term_days' => $system->pautang_payment_term_days,
                'pautang_max_active' => $system->pautang_max_active,
                'pautang_max_sacks' => $system->pautang_max_sacks,
                'pautang_grace_period_days' => $system->pautang_grace_period_days,
                'points_enabled' => $points->is_enabled,
                'completed_order_points' => $points->completed_order_points,
                'on_time_payment_points' => $points->on_time_payment_points,
                'peso_per_point' => $points->peso_per_point,
                'minimum_redemption' => $points->minimum_redemption,
                'maximum_points_usable' => $points->maximum_points_usable,
                'free_delivery_area_ids' => $freeDeliveryAreaIds,
                'low_stock_threshold' => $system->low_stock_threshold,
            ],
            'deliveryAreas' => $deliveryAreas,
        ]);
    }

    public function update(UpdateSystemSettingRequest $request): RedirectResponse
    {
        $attributes = $request->validated();
        $system = SystemSetting::current();
        $gcash = GcashSetting::query()->findOrNew(1);
        $gcash->id = 1;
        $oldLogoPath = $system->logo_path;
        $oldQrPath = $gcash->qr_code_path;
        $newLogoPath = $request->file('logo')?->store('business-logos', 'public');
        $newQrPath = $request->file('gcash_qr_code')?->store('gcash-qr', 'public');
        $changedFields = $this->changedFields($system, $gcash, $attributes, $newLogoPath, $newQrPath);

        try {
            DB::transaction(function () use ($system, $gcash, $attributes, $newLogoPath, $newQrPath, $changedFields, $request): void {
                $system->update([
                    'business_name' => trim($attributes['business_name']),
                    'logo_path' => $newLogoPath ?? $system->logo_path,
                    'contact_number' => $attributes['contact_number'] ? trim($attributes['contact_number']) : null,
                    'address' => $attributes['address'] ? trim($attributes['address']) : null,
                    'pautang_enabled' => $attributes['pautang_enabled'],
                    'pautang_installments' => $attributes['pautang_installments'],
                    'pautang_payment_term_days' => $attributes['pautang_payment_term_days'],
                    'pautang_max_active' => $attributes['pautang_max_active'],
                    'pautang_max_sacks' => $attributes['pautang_max_sacks'],
                    'pautang_grace_period_days' => $attributes['pautang_grace_period_days'],
                    'free_delivery_area_ids' => array_values(array_map('intval', $attributes['free_delivery_area_ids'] ?? [])),
                    'low_stock_threshold' => $attributes['low_stock_threshold'],
                ]);
                $gcash->fill([
                    'account_name' => $attributes['gcash_account_name'] ? trim($attributes['gcash_account_name']) : null,
                    'account_number' => $attributes['gcash_account_number'] ? trim($attributes['gcash_account_number']) : null,
                    'qr_code_path' => $newQrPath ?? $gcash->qr_code_path,
                ])->save();
                $this->points->settings()->update([
                    'is_enabled' => $attributes['points_enabled'],
                    'completed_order_points' => $attributes['completed_order_points'],
                    'on_time_payment_points' => $attributes['on_time_payment_points'],
                    'peso_per_point' => $attributes['peso_per_point'],
                    'minimum_redemption' => $attributes['minimum_redemption'],
                    'maximum_points_usable' => $attributes['maximum_points_usable'],
                ]);
                if ($changedFields !== []) {
                    $this->activityLogs->record(
                        $request->user(),
                        'settings',
                        'changed',
                        $system,
                        'System settings changed: '.implode(', ', $changedFields).'.',
                    );
                }
            });
        } catch (\Throwable $exception) {
            if ($newLogoPath) Storage::disk('public')->delete($newLogoPath);
            if ($newQrPath) Storage::disk('public')->delete($newQrPath);
            throw $exception;
        }

        if ($newLogoPath && $oldLogoPath) Storage::disk('public')->delete($oldLogoPath);
        if ($newQrPath && $oldQrPath) Storage::disk('public')->delete($oldQrPath);

        return to_route('system-settings.edit')->with('success', 'System settings updated successfully.');
    }

    /** @param array<string, mixed> $attributes
     *  @return list<string>
     */
    private function changedFields(SystemSetting $system, GcashSetting $gcash, array $attributes, ?string $newLogoPath, ?string $newQrPath): array
    {
        $points = $this->points->settings();
        $changes = [];
        $add = function (string $label, mixed $before, mixed $after) use (&$changes): void {
            if ($before !== $after) {
                $changes[] = $label;
            }
        };

        $add('Business name', $system->business_name, trim($attributes['business_name']));
        if ($newLogoPath) {
            $changes[] = 'Business logo';
        }
        $add('Contact number', $system->contact_number, $attributes['contact_number'] ? trim($attributes['contact_number']) : null);
        $add('Business address', $system->address, $attributes['address'] ? trim($attributes['address']) : null);
        $add('GCash account name', $gcash->account_name, $attributes['gcash_account_name'] ? trim($attributes['gcash_account_name']) : null);
        $add('GCash account number', $gcash->account_number, $attributes['gcash_account_number'] ? trim($attributes['gcash_account_number']) : null);
        if ($newQrPath) {
            $changes[] = 'GCash QR code';
        }
        $add('Pautang enabled', $system->pautang_enabled, $attributes['pautang_enabled']);
        $add('Pautang installments', $system->pautang_installments, $attributes['pautang_installments']);
        $add('Pautang payment term', $system->pautang_payment_term_days, $attributes['pautang_payment_term_days']);
        $add('Pautang maximum active orders', $system->pautang_max_active, $attributes['pautang_max_active']);
        $add('Pautang maximum sacks', $system->pautang_max_sacks, $attributes['pautang_max_sacks']);
        $add('Pautang grace period', $system->pautang_grace_period_days, $attributes['pautang_grace_period_days']);
        $add('Points enabled', $points->is_enabled, $attributes['points_enabled']);
        $add('Completed-order points', $points->completed_order_points, $attributes['completed_order_points']);
        $add('On-time payment points', $points->on_time_payment_points, $attributes['on_time_payment_points']);
        $add('Point-to-peso conversion', (float) $points->peso_per_point, (float) $attributes['peso_per_point']);
        $add('Minimum redemption', $points->minimum_redemption, $attributes['minimum_redemption']);
        $add('Maximum points usable', $points->maximum_points_usable, $attributes['maximum_points_usable']);
        $add('Free delivery areas', $system->free_delivery_area_ids ?? [], array_values(array_map('intval', $attributes['free_delivery_area_ids'] ?? [])));
        $add('Low-stock threshold', $system->low_stock_threshold, $attributes['low_stock_threshold']);

        return $changes;
    }
}
