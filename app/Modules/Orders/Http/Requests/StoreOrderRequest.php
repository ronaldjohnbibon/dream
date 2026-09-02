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
            'rice_product_id' => ['required', 'integer', 'exists:rice_products,id'],
            'quantity' => ['required', 'integer', 'min:1'],
            'payment_type' => ['required', 'string', Rule::in(Order::PAYMENT_TYPES)],
            'delivery_address' => ['required', 'string', 'max:2000'],
            'delivery_area' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
