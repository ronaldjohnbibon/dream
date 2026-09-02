<?php

namespace App\Modules\Orders\Policies;

use App\Modules\Orders\Models\Order;
use App\Modules\Users\Models\User;

class OrderPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Order $order): bool
    {
        return $user->is_admin || $order->customer_id === $user->id;
    }

    public function create(User $user): bool
    {
        return ! $user->is_admin && $user->account_status !== 'suspended';
    }

    public function update(User $user, Order $order): bool
    {
        return $user->is_admin;
    }
}
