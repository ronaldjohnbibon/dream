<?php

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Http\FormRequest;

class StoreGcashPaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Order $order */
        $order = $this->route('order');

        return $this->user() !== null
            && ! $this->user()->is_admin
            && $order->customer_id === $this->user()->id;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'pautang_installment_id' => ['nullable', 'integer', 'exists:pautang_installments,id'],
            'amount' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'reference_number' => ['required', 'string', 'max:100'],
            'screenshot' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
