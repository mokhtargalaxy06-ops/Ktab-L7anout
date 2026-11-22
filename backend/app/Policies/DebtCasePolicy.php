<?php

namespace App\Policies;

use App\Models\DebtCase;
use App\Models\User;
use App\Support\Access;

class DebtCasePolicy
{
    public function create(User $user): bool
    {
        return in_array('applications.submit', Access::abilities($user), true);
    }

    public function review(User $user): bool
    {
        return in_array('applications.review', Access::abilities($user), true);
    }

    public function own(User $user, DebtCase $case): bool
    {
        return $this->create($user) && $case->profile->user_id === $user->id;
    }

    public function assigned(User $user, DebtCase $case): bool
    {
        return $case->profile->user_id !== $user->id
            && $case->shop->status === 'verified'
            && in_array('verifications.respondAssigned', Access::abilities($user), true)
            && $case->shop->owners()->whereKey($user->id)->exists();
    }
}
