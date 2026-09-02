<?php

namespace App\Modules\Delivery\Policies;

use App\Modules\Delivery\Models\Delivery;
use App\Modules\Users\Models\User;

class DeliveryPolicy
{
    public function viewAny(User $user): bool { return $user->is_admin; }
    public function view(User $user, Delivery $delivery): bool { return $user->is_admin || $delivery->customer_id === $user->id; }
    public function update(User $user, Delivery $delivery): bool { return $user->is_admin; }
}
