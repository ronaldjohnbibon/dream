<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Models\RiceProduct;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRiceProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var RiceProduct $riceProduct */
        $riceProduct = $this->route('riceProduct');

        return $this->user()?->can('update', $riceProduct) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name'          => ['required', 'string', 'max:255'],
            'brand'         => ['required', 'string', 'max:255'],
            'description'   => ['nullable', 'string', 'max:2000'],
            'cost_price'    => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
            'selling_price' => ['required', 'numeric', 'min:0', 'max:9999999999.99', 'decimal:0,2'],
        ];
    }
}
