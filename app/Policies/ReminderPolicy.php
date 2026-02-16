<?php

namespace App\Policies;

use App\Models\Reminder;
use App\Models\User;

class ReminderPolicy extends TenantPolicy
{
    protected function canAccessReminder(User $user, Reminder $reminder): bool
    {
        return $this->userMatchesTenant($user)
            && (int) $reminder->therapy()->withoutGlobalScopes()->value('pharmacy_id') === $this->currentPharmacyId();
    }

    public function view(User $user, Reminder $reminder): bool { return $this->canAccessReminder($user, $reminder); }
    public function update(User $user, Reminder $reminder): bool { return $this->canAccessReminder($user, $reminder); }
    public function delete(User $user, Reminder $reminder): bool { return $this->canAccessReminder($user, $reminder); }
}
