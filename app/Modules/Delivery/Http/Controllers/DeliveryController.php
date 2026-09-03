<?php

namespace App\Modules\Delivery\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Delivery\Http\Requests\UpdateDeliveryRequest;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Models\DeliveryArea;
use App\Modules\Delivery\Services\DeliveryService;
use App\Modules\Delivery\Services\DeliveryPricingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryController extends Controller
{
    public function __construct(private readonly DeliveryService $deliveries, private readonly DeliveryPricingService $pricing) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Delivery::class);
        $filters = $request->validate([
            'status' => ['nullable', 'in:all,'.implode(',', Delivery::STATUSES)],
            'delivery_date' => ['nullable', 'date'],
        ]);
        $status = $filters['status'] ?? 'all';
        $deliveryDate = $filters['delivery_date'] ?? '';
        $deliveries = Delivery::query()
            ->with(['order:id,order_number,quantity,final_amount', 'customer:id,name,email,mobile_number'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->when($deliveryDate !== '', fn ($query) => $query->whereDate('delivery_date', $deliveryDate))
            ->orderByRaw('delivery_date is null, delivery_date')
            ->latest('id')
            ->paginate(15)->withQueryString()
            ->through(fn (Delivery $delivery) => $this->deliveryData($delivery));

        return Inertia::render('modules/deliveries/Index', [
            'deliveries' => $deliveries,
            'filters' => ['status' => $status, 'delivery_date' => $deliveryDate],
        ]);
    }

    public function show(Delivery $delivery): Response
    {
        $this->authorize('view', $delivery);
        $delivery->load(['order.riceProduct', 'customer:id,name,email,mobile_number', 'deliveryArea']);
        $canManage = request()->user()->is_admin;
        return Inertia::render('modules/deliveries/Show', [
            'delivery' => $this->deliveryData($delivery),
            'canManage' => $canManage,
            'allowedStatuses' => $canManage ? $this->deliveries->allowedStatuses($delivery) : [],
            'areas' => $canManage ? DeliveryArea::query()->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $delivery->delivery_area_id))->orderBy('name')->get()->map(fn (DeliveryArea $area) => $this->areaData($area)) : [],
        ]);
    }

    public function update(UpdateDeliveryRequest $request, Delivery $delivery): RedirectResponse
    {
        $this->deliveries->update($delivery, $request->validated(), $request->user());
        return to_route('deliveries.show', $delivery)->with('success', 'Delivery updated successfully.');
    }

    /** @return array<string, mixed> */
    private function deliveryData(Delivery $delivery): array
    {
        return [
            'id' => $delivery->id,
            'order' => ['id' => $delivery->order->id, 'order_number' => $delivery->order->order_number, 'quantity' => $delivery->order->quantity, 'final_amount' => $delivery->order->final_amount],
            'customer' => ['id' => $delivery->customer->id, 'name' => $delivery->customer->name, 'email' => $delivery->customer->email, 'mobile_number' => $delivery->customer->mobile_number],
            'delivery_area_id' => $delivery->delivery_area_id,
            'delivery_area_name' => $delivery->delivery_area_name,
            'delivery_address' => $delivery->delivery_address,
            'delivery_fee' => $delivery->delivery_fee,
            'delivery_date' => $delivery->delivery_date?->toDateString(),
            'delivery_person' => $delivery->delivery_person,
            'status' => $delivery->status,
            'notes' => $delivery->notes,
            'delivered_date' => $delivery->delivered_date?->toDateString(),
            'rice_product' => $delivery->order->relationLoaded('riceProduct') ? ['name' => $delivery->order->riceProduct->name, 'brand' => $delivery->order->riceProduct->brand] : null,
        ];
    }

    /** @return array<string, mixed> */
    private function areaData(DeliveryArea $area): array
    {
        return ['id' => $area->id, 'name' => $area->name, 'delivery_fee' => number_format($this->pricing->feeFor($area), 2, '.', '')];
    }
}
