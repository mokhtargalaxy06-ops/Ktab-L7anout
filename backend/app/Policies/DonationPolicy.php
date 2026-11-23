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
        return $this->viewAny($user) && $donation->donor_id === $user->id;
    }

    public function viewAny(User $user): bool
    {
        return in_array('donations.viewOwn', Access::abilities($user), true);
    }

    public function simulate(User $user, Donation $donation): bool
    {
        return $this->create($user) && $this->view($user, $donation);
    }

    public function manageReturns(User $user): bool
    {
        return in_array('financialRecords.review', Access::abilities($user), true);
    }
}
