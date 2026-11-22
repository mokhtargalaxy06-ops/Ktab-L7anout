<?php

namespace App\Policies;

use App\Models\Donation;
use App\Models\User;
use App\Support\Access;

class DonationPolicy
{
    public function create(User $user): bool
    {
        return in_array('donations.create', Access::abilities($user), true);
    }

    public function view(User $user, Donation $donation): bool
    {
        return $this->create($user) && $donation->donor_id === $user->id;
    }

    public function manageReturns(User $user): bool
    {
        return in_array('financialRecords.review', Access::abilities($user), true);
    }
}
