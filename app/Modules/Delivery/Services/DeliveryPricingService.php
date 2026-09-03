<?php

namespace App\Modules\Delivery\Services;

use App\Modules\Delivery\Models\DeliveryArea;
use App\Modules\Settings\Models\SystemSetting;

class DeliveryPricingService
{
    public function feeFor(DeliveryArea $area): float
    {
        return SystemSetting::current()->isFreeDeliveryArea($area->id) ? 0.0 : (float) $area->delivery_fee;
    }
}
