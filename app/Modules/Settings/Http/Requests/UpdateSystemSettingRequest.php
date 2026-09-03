<?php

namespace App\Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSystemSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'business_name' => ['required', 'string', 'max:255'],
            'logo' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'contact_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:2000'],
            'gcash_account_name' => ['nullable', 'string', 'max:255'],
            'gcash_account_number' => ['nullable', 'string', 'max:50'],
            'gcash_qr_code' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'pautang_enabled' => ['required', 'boolean'],
            'pautang_installments' => ['required', 'integer', 'min:1', 'max:12'],
            'pautang_payment_term_days' => ['required', 'integer', 'min:1', 'max:365', 'gte:pautang_installments'],
            'pautang_max_active' => ['required', 'integer', 'min:1', 'max:100'],
            'pautang_max_sacks' => ['required', 'integer', 'min:1', 'max:100'],
            'pautang_grace_period_days' => ['required', 'integer', 'min:0', 'max:30'],
            'points_enabled' => ['required', 'boolean'],
            'completed_order_points' => ['required', 'integer', 'min:0', 'max:1000000'],
            'on_time_payment_points' => ['required', 'integer', 'min:0', 'max:1000000'],
            'peso_per_point' => ['required', 'numeric', 'min:0', 'max:1000000', 'decimal:0,2'],
            'minimum_redemption' => ['required', 'integer', 'min:1', 'max:1000000'],
            'maximum_points_usable' => ['required', 'integer', 'gte:minimum_redemption', 'max:1000000'],
            'free_delivery_area_ids' => ['nullable', 'array'],
            'free_delivery_area_ids.*' => ['integer', Rule::exists('delivery_areas', 'id')->where('is_active', true)],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:1000000'],
        ];
    }
}
