<?php

namespace App\Support;

use App\Models\User;

class Access
{
    public const PUBLIC_ROLES = ['donor', 'beneficiary', 'shopkeeper'];

    private const ABILITIES = [
        'donor' => ['cases.browse', 'donations.viewOwn', 'donations.create'],
        'beneficiary' => ['applications.submit', 'applications.viewOwn', 'applications.uploadOwn'],
        'shopkeeper' => ['shops.manageAssigned', 'verifications.viewAssigned', 'verifications.respondAssigned', 'settlements.confirmAssigned'],
        'administrator' => ['users.review', 'users.changeStatus', 'applications.review', 'cases.manage', 'financialRecords.review', 'geography.manage', 'shops.review'],
    ];

    public static function abilities(User $user): array
    {
        if ($user->status !== 'active') {
            return [];
        }

        $abilities = ['profile.update'];
        foreach ($user->roles as $role) {
            foreach (self::ABILITIES[$role->code] ?? [] as $ability) {
                if ($user->hasVerifiedEmail() || in_array($role->code, self::PUBLIC_ROLES, true)
                    || ($role->code === 'administrator' && app()->environment('local'))) {
                    $abilities[] = $ability;
                }
            }
        }

        return array_values(array_unique($abilities));
    }
}
