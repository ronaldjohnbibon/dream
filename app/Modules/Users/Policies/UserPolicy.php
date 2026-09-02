<?php

namespace App\Modules\Users\Policies;

use App\Modules\Users\Models\User;

class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->is_admin;
    }

    public function view(User $actor, User $user): bool
    {
        return $actor->is_admin;
    }

    public function create(User $actor): bool
    {
        return $actor->is_admin;
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->is_admin;
    }

    public function delete(User $actor, User $user): bool
    {
        return $actor->is_admin && ! $actor->is($user);
    }
}
