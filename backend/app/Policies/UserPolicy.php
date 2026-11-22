<?php

namespace App\Policies;

use App\Models\User;
use App\Support\Access;

class UserPolicy
{
    public function update(User $actor, User $target): bool
    {
        return $actor->is($target) && in_array('profile.update', Access::abilities($actor), true);
    }

    public function viewAny(User $actor): bool
    {
        return in_array('users.review', Access::abilities($actor), true);
    }

    public function changeStatus(User $actor, User $target): bool
    {
        return ! $actor->is($target)
            && in_array('users.changeStatus', Access::abilities($actor), true)
            && ! $target->roles->contains('code', 'administrator');
    }
}
