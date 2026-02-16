<?php

namespace App\Policies;

use App\Models\Therapy;
use App\Models\User;

class TherapyPolicy extends TenantPolicy
{
    public function view(User $user, Therapy $therapy): bool { return $this->canAccessModel($user, $therapy); }
    public function update(User $user, Therapy $therapy): bool { return $this->canAccessModel($user, $therapy); }
    public function delete(User $user, Therapy $therapy): bool { return $this->canAccessModel($user, $therapy); }
}
