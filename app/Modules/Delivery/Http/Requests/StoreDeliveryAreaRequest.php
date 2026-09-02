<?php

namespace App\Modules\Delivery\Http\Requests;

use App\Modules\Delivery\Models\DeliveryArea;
use Illuminate\Foundation\Http\FormRequest;

class StoreDeliveryAreaRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()?->can('create', DeliveryArea::class) ?? false; }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255', 'unique:delivery_areas,name'],
            'delivery_fee' => ['required', 'numeric', 'min:0', 'max:999999.99'],
        ];
    }
}
