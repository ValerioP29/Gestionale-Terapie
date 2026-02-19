<?php

namespace App\Services\Reminders;

use App\Models\TherapyReminder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class ReminderService
{
    public function __construct(private readonly ComputeNextDueAt $computeNextDueAt)
    {
    }

    public function markDone(TherapyReminder $reminder): TherapyReminder
    {
        $lock = Cache::lock(sprintf('therapy-reminder:done:%d', $reminder->id), 10);

        return $lock->block(3, function () use ($reminder): TherapyReminder {
            $freshReminder = TherapyReminder::withoutGlobalScopes()
                ->whereKey($reminder->id)
                ->where('pharmacy_id', $reminder->pharmacy_id)
                ->firstOrFail();

            if ($freshReminder->status === 'done' && $freshReminder->frequency === 'one_shot') {
                return $freshReminder;
            }

            if ($freshReminder->status !== 'active') {
                return $freshReminder;
            }

            $reference = $freshReminder->next_due_at ?? $freshReminder->first_due_at;
            $referenceAt = $reference !== null
                ? CarbonImmutable::instance($reference)
                : CarbonImmutable::now();

            $doneAt = CarbonImmutable::now();
            $nextDueAt = $this->computeNextDueAt->execute(
                $referenceAt,
                $freshReminder->frequency,
                $freshReminder->weekday,
            );

            $freshReminder->fill([
                'last_done_at' => $doneAt,
                'next_due_at' => $nextDueAt,
                'status' => $freshReminder->frequency === 'one_shot' ? 'done' : 'active',
            ]);
            $freshReminder->save();

            return $freshReminder->refresh();
        });
    }
}
