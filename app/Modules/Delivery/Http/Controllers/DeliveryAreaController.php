<?php

namespace App\Modules\Delivery\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Delivery\Http\Requests\StoreDeliveryAreaRequest;
use App\Modules\Delivery\Http\Requests\UpdateDeliveryAreaRequest;
use App\Modules\Delivery\Models\DeliveryArea;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryAreaController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', DeliveryArea::class);

        return Inertia::render('modules/delivery-areas/Index', [
            'areas' => DeliveryArea::query()->orderByDesc('is_active')->orderBy('name')->get()->map(fn (DeliveryArea $area) => $this->areaData($area)),
        ]);
    }

    public function store(StoreDeliveryAreaRequest $request): RedirectResponse
    {
        DeliveryArea::create([...$request->validated(), 'is_active' => true]);

        return to_route('delivery-areas.index')->with('success', 'Delivery area added successfully.');
    }

    public function update(UpdateDeliveryAreaRequest $request, DeliveryArea $deliveryArea): RedirectResponse
    {
        $deliveryArea->update($request->validated());

        return to_route('delivery-areas.index')->with('success', 'Delivery area updated successfully.');
    }

    public function updateStatus(Request $request, DeliveryArea $deliveryArea): RedirectResponse
    {
        $this->authorize('update', $deliveryArea);
        $attributes = $request->validate(['is_active' => ['required', 'boolean']]);
        $deliveryArea->update($attributes);

        return to_route('delivery-areas.index')->with('success', 'Delivery area status updated successfully.');
    }

    /** @return array<string, mixed> */
    private function areaData(DeliveryArea $area): array
    {
        return ['id' => $area->id, 'name' => $area->name, 'delivery_fee' => $area->delivery_fee, 'is_active' => $area->is_active];
    }
}
