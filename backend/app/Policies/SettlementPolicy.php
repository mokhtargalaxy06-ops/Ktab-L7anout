<?php

namespace App\Policies;

use App\Models\Shop;
use App\Models\User;
use App\Support\Access;

class SettlementPolicy
{
    public function administer(User $user): bool
    {
        return in_array('financialRecords.review', Access::abilities($user), true);
    }

    public function browseShop(User $user): bool
    {
        return in_array('settlements.confirmAssigned', Access::abilities($user), true);
    }

    public function respond(User $user, string $shop): bool
    {
        return $this->browseShop($user) && $this->ownsShop($user, $shop);
    }

    public function ownsShop(User $user, string $shop): bool
    {
        return (bool) Shop::find($shop)?->owners()->whereKey($user->id)->exists();
    }
}
