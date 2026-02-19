<?php

namespace App\Console\Commands;

use App\Models\ReminderDispatch;
use App\Models\TherapyReminder;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Throwable;

class DispatchDueRemindersCommand extends Command
{
    protected $signature = 'reminders:dispatch-due';

    protected $description = 'Queue due reminders into reminder dispatch table';

    public function handle(): int
    {
        $now = Carbon::now();

        TherapyReminder::withoutGlobalScopes()
            ->where('status', 'active')
            ->whereRaw('COALESCE(next_due_at, first_due_at) <= ?', [$now])
            ->orderBy('id')
            ->chunkById(100, function ($reminders): void {
                foreach ($reminders as $reminder) {
                    $dueAt = $reminder->next_due_at ?? $reminder->first_due_at;

                    if ($dueAt === null) {
                        continue;
                    }

                    $lock = Cache::lock(sprintf('therapy-reminder:dispatch:%d:%s', $reminder->id, $dueAt->toIso8601String()), 10);

                    try {
                        if (! $lock->get()) {
                            continue;
                        }

                        ReminderDispatch::withoutGlobalScopes()->firstOrCreate(
                            [
                                'reminder_id' => $reminder->id,
                                'due_at' => $dueAt,
                            ],
                            [
                                'pharmacy_id' => $reminder->pharmacy_id,
                                'attempt' => 0,
                                'outcome' => 'pending',
                            ]
                        );
                    } catch (Throwable) {
                        continue;
                    } finally {
                        optional($lock)->release();
                    }
                }
            });

        $this->info('Due reminders dispatched.');

        return self::SUCCESS;
    }
}
