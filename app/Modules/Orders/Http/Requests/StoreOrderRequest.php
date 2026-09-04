<?php

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Order::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'idempotency_key'  => ['required', 'uuid'],
            'rice_product_id'  => ['required', 'integer', 'exists:rice_products,id'],
            'quantity'         => ['required', 'integer', 'min:1'],
            'points_to_use'    => ['nullable', 'integer', 'min:0'],
            'delivery_address' => ['required', 'string', 'max:2000'],
            'delivery_area_id' => ['required', 'integer', Rule::exists('delivery_areas', 'id')->where('is_active', true)],
            'notes'            => ['nullable', 'string', 'max:2000'],
        ];
    }
}
