<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public function log(
        int $pharmacyId,
        string $action,
        Model $subject,
        array $meta = [],
        ?int $actorUserId = null,
    ): AuditLog {
        return AuditLog::query()->create([
            'pharmacy_id' => $pharmacyId,
            'actor_user_id' => $actorUserId ?? Auth::id(),
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'meta' => $meta,
            'created_at' => now(),
        ]);
    }
}
