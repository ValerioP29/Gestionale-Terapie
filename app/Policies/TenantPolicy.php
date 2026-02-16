<?php

namespace App\Policies;

use App\Tenancy\CurrentPharmacy;
use App\Models\User;

abstract class TenantPolicy
{
    protected function currentPharmacyId(): ?int
    {
        return app(CurrentPharmacy::class)->getId();
    }

    protected function userMatchesTenant(User $user): bool
    {
        $current = $this->currentPharmacyId();
        $userPharmacyId = data_get($user, 'pharmacy_id');

        if ($current === null) {
            return false;
        }

        if ($userPharmacyId === null || $userPharmacyId === '') {
            return true;
        }

        return (int) $userPharmacyId === $current;
    }

    public function viewAny(User $user): bool
    {
        return $this->userMatchesTenant($user);
    }

    public function create(User $user): bool
    {
        return $this->userMatchesTenant($user);
    }

    protected function canAccessModel(User $user, object $model): bool
    {
        return $this->userMatchesTenant($user) && ((int) data_get($model, 'pharmacy_id') === $this->currentPharmacyId());
    }
}
