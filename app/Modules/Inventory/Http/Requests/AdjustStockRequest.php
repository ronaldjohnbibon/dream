<?php

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Models\RiceProduct;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var RiceProduct $riceProduct */
        $riceProduct = $this->route('riceProduct');

        return $this->user()?->can('adjust', $riceProduct) ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'idempotency_key'      => ['required', 'uuid'],
            'type'                 => ['required', 'string', Rule::in(StockMovement::TYPES)],
            'quantity'             => ['required', 'integer', 'min:1'],
            'adjustment_direction' => ['nullable', 'required_if:type,adjustment', Rule::in(['increase', 'decrease'])],
            'order_id'             => ['nullable', 'integer', 'min:1'],
            'notes'                => ['nullable', 'string', 'max:2000'],
        ];
    }
}
