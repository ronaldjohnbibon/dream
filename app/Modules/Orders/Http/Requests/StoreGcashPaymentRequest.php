<?php

namespace App\Modules\Orders\Http\Requests;

use App\Modules\Orders\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

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
            'idempotency_key'        => ['required', 'uuid'],
            'pautang_installment_id' => ['required', 'integer', 'exists:pautang_installments,id'],
            'amount'                 => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'reference_number'       => ['required', 'string', 'regex:/^\d{13}$/'],
            'screenshot'             => [
                'required',
                File::image()
                    ->types(['image/jpeg', 'image/png'])
                    ->extensions(['jpg', 'jpeg', 'png'])
                    ->max('5mb'),
            ],
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
        ];
    }
}
