<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Models\RiceProduct;
use App\Modules\Inventory\Models\StockMovement;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockMovementController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', RiceProduct::class);

        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'type'   => ['nullable', 'in:all,'.implode(',', StockMovement::TYPES)],
        ]);

        $search = $filters['search'] ?? '';
        $type   = $filters['type']   ?? 'all';

        $movements = StockMovement::query()
            ->with(['riceProduct:id,name,brand', 'user:id,name'])
            ->when($search !== '', fn ($query) => $query->whereHas('riceProduct', fn ($productQuery) => $productQuery
                ->where('name', 'like', "%{$search}%")
                ->orWhere('brand', 'like', "%{$search}%")))
            ->when($type !== 'all', fn ($query) => $query->where('type', $type))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (StockMovement $movement) => $this->movementData($movement));

        return Inertia::render('modules/inventory/Movements', [
            'movements' => $movements,
            'filters'   => ['search' => $search, 'type' => $type],
        ]);
    }

    /** @return array{id: int, rice_product: array{id: int, name: string, brand: string}, quantity: int, type: string, previous_stock: int, new_stock: int, order_id: int|null, notes: string|null, user_name: string, created_at: string} */
    private function movementData(StockMovement $movement): array
    {
        return [
            'id'           => $movement->id,
            'rice_product' => [
                'id'    => $movement->riceProduct->id,
                'name'  => $movement->riceProduct->name,
                'brand' => $movement->riceProduct->brand,
            ],
            'quantity'       => $movement->quantity,
            'type'           => $movement->type,
            'previous_stock' => $movement->previous_stock,
            'new_stock'      => $movement->new_stock,
            'order_id'       => $movement->order_id,
            'notes'          => $movement->notes,
            'user_name'      => $movement->user?->name ?? 'Deleted user',
            'created_at'     => $movement->created_at->toISOString(),
        ];
    }
}
