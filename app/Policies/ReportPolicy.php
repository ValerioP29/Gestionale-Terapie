<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;

class ReportPolicy extends TenantPolicy
{
    public function view(User $user, Report $report): bool { return $this->canAccessModel($user, $report); }
    public function update(User $user, Report $report): bool { return $this->canAccessModel($user, $report); }
    public function delete(User $user, Report $report): bool { return $this->canAccessModel($user, $report); }
}
