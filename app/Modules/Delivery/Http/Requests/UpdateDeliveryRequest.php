<?php

namespace App\Modules\Delivery\Http\Requests;

use App\Modules\Delivery\Models\Delivery;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDeliveryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('delivery')) ?? false;
    }

    public function rules(): array
    {
        return [
            'delivery_area_id' => ['required', 'integer', Rule::exists('delivery_areas', 'id')],
            'delivery_address' => ['required', 'string', 'max:2000'],
            'delivery_date' => ['nullable', 'date'],
            'delivery_person' => ['nullable', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(Delivery::STATUSES)],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (in_array($this->input('status'), ['scheduled', 'preparing', 'out_for_delivery', 'delivered'], true) && ! $this->input('delivery_date')) {
                $validator->errors()->add('delivery_date', 'A delivery date is required once delivery is scheduled.');
            }
        });
    }
}
