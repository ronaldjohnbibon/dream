<?php

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Order $order */
        $order = $this->route('order');

        return $this->user()?->can('update', $order) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'order_status' => ['required', 'string', Rule::in(Order::STATUSES)],
            'payment_status' => ['required', 'string', Rule::in(Order::PAYMENT_STATUSES)],
            'delivery_date' => ['nullable', 'date'],
        ];
    }
}
