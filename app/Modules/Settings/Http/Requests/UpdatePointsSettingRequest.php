<?php

namespace App\Modules\Settings\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePointsSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->is_admin ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'completed_order_points' => ['required', 'integer', 'min:0', 'max:1000000'],
            'on_time_payment_points' => ['required', 'integer', 'min:0', 'max:1000000'],
            'peso_per_point' => ['required', 'numeric', 'min:0', 'max:1000000', 'decimal:0,2'],
        ];
    }
}
