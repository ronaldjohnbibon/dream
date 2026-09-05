<?php

namespace App\Modules\Inventory\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Inventory\Http\Requests\AdjustStockRequest;
use App\Modules\Inventory\Http\Requests\StoreRiceProductRequest;
use App\Modules\Inventory\Http\Requests\UpdateRiceProductRequest;
use App\Modules\Inventory\Models\RiceProduct;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Logs\Services\ActivityLogger;
use App\Modules\Settings\Models\SystemSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RiceProductController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogs) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', RiceProduct::class);

        $filters = $request->validate([
            'search'    => ['nullable', 'string', 'max:100'],
            'status'    => ['nullable', 'in:all,active,inactive'],
            'low_stock' => ['nullable', 'in:0,1'],
        ]);

        $search            = $filters['search'] ?? '';
        $status            = $filters['status'] ?? 'all';
        $lowStock          = ($filters['low_stock'] ?? '0') === '1';
        $lowStockThreshold = SystemSetting::current()->low_stock_threshold;

        $products = RiceProduct::query()
            ->when($search !== '', fn ($query) => $query->where(fn ($searchQuery) => $searchQuery
                ->where('name', 'like', "%{$search}%")
                ->orWhere('brand', 'like', "%{$search}%")))
            ->when($status === 'active', fn ($query) => $query->where('is_active', true))
            ->when($status === 'inactive', fn ($query) => $query->where('is_active', false))
            ->when($lowStock, fn ($query) => $query
                ->where('is_active', true)
                ->where('available_stock', '<=', $lowStockThreshold))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (RiceProduct $product) => $this->riceProductData($product));

        return Inertia::render('modules/inventory/Index', [
            'products' => $products,
            'filters'  => [
                'search'    => $search,
                'status'    => $status,
                'low_stock' => $lowStock,
            ],
            'lowStockThreshold' => $lowStockThreshold,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', RiceProduct::class);

        return Inertia::render('modules/inventory/Create');
    }

    public function store(StoreRiceProductRequest $request): RedirectResponse
    {
        $attributes   = $request->validated();
        $initialStock = $attributes['initial_stock'];
        unset($attributes['initial_stock']);

        $product = DB::transaction(function () use ($attributes, $initialStock, $request): RiceProduct {
            $product = RiceProduct::create([
                ...$attributes,
                'sack_size'       => RiceProduct::SACK_SIZE,
                'available_stock' => $initialStock,
                'reserved_stock'  => 0,
                'is_active'       => true,
            ]);

            if ($initialStock > 0) {
                $product->stockMovements()->create([
                    'quantity'       => $initialStock,
                    'type'           => 'stock_in',
                    'previous_stock' => 0,
                    'new_stock'      => $initialStock,
                    'user_id'        => $request->user()->id,
                ]);
            }

            return $product;
        });

        return to_route('rice-products.show', $product)->with('success', 'Rice product created successfully.');
    }

    public function show(RiceProduct $riceProduct): Response
    {
        $this->authorize('view', $riceProduct);

        $recentMovements = $riceProduct->stockMovements()
            ->with('user:id,name')
            ->latest()
            ->limit(10)
            ->get()
            ->map(fn (StockMovement $movement) => $this->movementData($movement));

        return Inertia::render('modules/inventory/Show', [
            'product'         => $this->riceProductData($riceProduct),
            'recentMovements' => $recentMovements,
        ]);
    }

    public function edit(RiceProduct $riceProduct): Response
    {
        $this->authorize('update', $riceProduct);

        return Inertia::render('modules/inventory/Edit', [
            'product' => $this->riceProductData($riceProduct),
        ]);
    }

    public function update(UpdateRiceProductRequest $request, RiceProduct $riceProduct): RedirectResponse
    {
        $riceProduct->update($request->validated());

        return to_route('rice-products.show', $riceProduct)->with('success', 'Rice product updated successfully.');
    }

    public function updateStatus(RiceProduct $riceProduct): RedirectResponse
    {
        $this->authorize('update', $riceProduct);

        $riceProduct->update(['is_active' => ! $riceProduct->is_active]);

        return back()->with('success', $riceProduct->is_active ? 'Rice product activated.' : 'Rice product deactivated.');
    }

    public function createStockEntry(RiceProduct $riceProduct): Response
    {
        $this->authorize('adjust', $riceProduct);

        return Inertia::render('modules/inventory/StockEntry', [
            'product' => $this->riceProductData($riceProduct),
        ]);
    }

    public function storeStockEntry(AdjustStockRequest $request, RiceProduct $riceProduct): RedirectResponse
    {
        $attributes = $request->validated();

        DB::transaction(function () use ($attributes, $riceProduct, $request): void {
            $product = RiceProduct::query()->lockForUpdate()->findOrFail($riceProduct->id);
            if (StockMovement::query()->where('idempotency_key', $attributes['idempotency_key'])->exists()) {
                return;
            }

            $previousStock = $product->available_stock;
            $newStock      = $previousStock + $this->stockChange($attributes);

            if ($newStock < 0) {
                throw ValidationException::withMessages([
                    'quantity' => 'This movement would make available stock negative.',
                ]);
            }

            $product->update(['available_stock' => $newStock]);
            $movement = $product->stockMovements()->create([
                'idempotency_key' => $attributes['idempotency_key'],
                'quantity'        => $attributes['quantity'],
                'type'            => $attributes['type'],
                'previous_stock'  => $previousStock,
                'new_stock'       => $newStock,
                'order_id'        => $attributes['order_id'] ?? null,
                'notes'           => $attributes['notes']    ?? null,
                'user_id'         => $request->user()->id,
            ]);
            $movementType = str_replace('_', ' ', $attributes['type']);
            $this->activityLogs->record(
                $request->user(),
                'inventory',
                'adjusted',
                $movement,
                "Inventory {$movementType} for {$product->name}: {$attributes['quantity']} sack(s), stock changed from {$previousStock} to {$newStock}.",
            );
        });

        return to_route('rice-products.show', $riceProduct)->with('success', 'Stock movement recorded successfully.');
    }

    /** @param array<string, mixed> $attributes */
    private function stockChange(array $attributes): int
    {
        $quantity = $attributes['quantity'];

        return match ($attributes['type']) {
            'stock_in', 'cancellation', 'returned' => $quantity,
            'order', 'damaged'                     => -$quantity,
            'adjustment'                           => $attributes['adjustment_direction'] === 'increase' ? $quantity : -$quantity,
        };
    }

    /** @return array{id: int, name: string, brand: string, description: string|null, sack_size: string, cost_price: string, selling_price: string, available_stock: int, reserved_stock: int, is_active: bool, created_at: string, updated_at: string} */
    private function riceProductData(RiceProduct $product): array
    {
        return [
            'id'              => $product->id,
            'name'            => $product->name,
            'brand'           => $product->brand,
            'description'     => $product->description,
            'sack_size'       => $product->sack_size,
            'cost_price'      => $product->cost_price,
            'selling_price'   => $product->selling_price,
            'available_stock' => $product->available_stock,
            'reserved_stock'  => $product->reserved_stock,
            'is_active'       => $product->is_active,
            'created_at'      => $product->created_at->toISOString(),
            'updated_at'      => $product->updated_at->toISOString(),
        ];
    }

    /** @return array{id: int, quantity: int, type: string, previous_stock: int, new_stock: int, order_id: int|null, notes: string|null, user_name: string, created_at: string} */
    private function movementData(StockMovement $movement): array
    {
        return [
            'id'             => $movement->id,
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
