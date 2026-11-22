<?php

namespace App\Policies;

use App\Models\Shop;
use App\Models\User;
use App\Support\Access;

class ShopPolicy
{
    public function create(User $user): bool
    {
        return in_array('shops.manageAssigned', Access::abilities($user), true);
    }

    public function own(User $user, Shop $shop): bool
    {
        return $this->create($user) && $shop->owners()->whereKey($user->id)->exists();
    }

    public function update(User $user, Shop $shop): bool
    {
        return $this->own($user, $shop) && in_array($shop->status, ['pending', 'rejected', 'verified'], true);
    }

    public function review(User $user): bool
    {
        return in_array('shops.review', Access::abilities($user), true);
    }

    public function view(User $user, Shop $shop): bool
    {
        return $this->own($user, $shop) || $this->review($user);
    }

    public function respond(User $user, Shop $shop): bool
    {
        return $shop->status === 'verified' && $this->own($user, $shop);
    }
}
