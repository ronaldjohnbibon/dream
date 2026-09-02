<?php

namespace App\Modules\Inventory\Policies;

use App\Modules\Inventory\Models\RiceProduct;
use App\Modules\Users\Models\User;

class RiceProductPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, RiceProduct $riceProduct): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, RiceProduct $riceProduct): bool
    {
        return $user->is_admin;
    }

    public function adjust(User $user, RiceProduct $riceProduct): bool
    {
        return $user->is_admin;
    }
}
