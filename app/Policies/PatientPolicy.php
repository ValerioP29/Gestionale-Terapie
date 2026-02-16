<?php

namespace App\Policies;

use App\Models\Patient;
use App\Models\User;

class PatientPolicy extends TenantPolicy
{
    public function view(User $user, Patient $patient): bool { return $this->canAccessModel($user, $patient); }
    public function update(User $user, Patient $patient): bool { return $this->canAccessModel($user, $patient); }
    public function delete(User $user, Patient $patient): bool { return $this->canAccessModel($user, $patient); }
}
