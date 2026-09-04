<?php

namespace App\Modules\Delivery\Policies;

use App\Modules\Delivery\Models\DeliveryArea;
use App\Modules\Users\Models\User;

class DeliveryAreaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, DeliveryArea $deliveryArea): bool
    {
        return $user->is_admin;
    }
}
