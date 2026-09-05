<?php

namespace App\Modules\Delivery\Http\Requests;

use App\Modules\Delivery\Models\DeliveryArea;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeliveryAreaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('deliveryArea')) ?? false;
    }

    public function rules(): array
    {
        /** @var DeliveryArea $area */
        $area = $this->route('deliveryArea');

        return [
            'name'         => ['required', 'string', 'max:255', Rule::unique('delivery_areas', 'name')->ignore($area)],
            'delivery_fee' => ['required', 'numeric', 'min:0', 'max:999999.99', 'decimal:0,2'],
        ];
    }
}
