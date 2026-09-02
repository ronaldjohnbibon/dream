<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Models\RiceProduct;
use Illuminate\Foundation\Http\FormRequest;

class StoreRiceProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', RiceProduct::class) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'cost_price' => ['required', 'numeric', 'min:0'],
            'selling_price' => ['required', 'numeric', 'min:0'],
            'initial_stock' => ['required', 'integer', 'min:0'],
            'reorder_level' => ['required', 'integer', 'min:0'],
        ];
    }
}
