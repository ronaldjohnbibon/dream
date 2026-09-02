<?php

namespace App\Modules\Orders\Http\Requests;

class RejectGcashPaymentRequest extends ReviewGcashPaymentRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'remarks' => ['required', 'string', 'max:2000'],
        ];
    }
}
