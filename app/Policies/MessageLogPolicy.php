<?php

namespace App\Policies;

use App\Models\MessageLog;
use App\Models\User;

class MessageLogPolicy extends TenantPolicy
{
    protected function canAccessMessageLog(User $user, MessageLog $messageLog): bool
    {
        return $this->userMatchesTenant($user)
            && ((int) $messageLog->pharma_id === $this->currentPharmacyId());
    }

    public function view(User $user, MessageLog $messageLog): bool
    {
        return $this->canAccessMessageLog($user, $messageLog);
    }

    public function update(User $user, MessageLog $messageLog): bool
    {
        return false;
    }

    public function delete(User $user, MessageLog $messageLog): bool
    {
        return false;
    }
}
