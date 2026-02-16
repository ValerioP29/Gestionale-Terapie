<?php

namespace App\Policies;

use App\Models\Followup;
use App\Models\User;

class FollowupPolicy extends TenantPolicy
{
    public function view(User $user, Followup $followup): bool { return $this->canAccessModel($user, $followup); }
    public function update(User $user, Followup $followup): bool { return $this->canAccessModel($user, $followup); }
    public function delete(User $user, Followup $followup): bool { return $this->canAccessModel($user, $followup); }
}
