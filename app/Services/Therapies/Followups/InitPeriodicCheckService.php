<?php

namespace App\Services\Therapies\Followups;

use App\Models\Therapy;
use App\Models\TherapyChecklistAnswer;
use App\Models\TherapyFollowup;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class InitPeriodicCheckService
{
    public function handle(Therapy $therapy): TherapyFollowup
    {
        $romeNow = CarbonImmutable::now('Europe/Rome');
        $startOfDayUtc = $romeNow->startOfDay()->setTimezone('UTC');
        $endOfDayUtc = $romeNow->endOfDay()->setTimezone('UTC');

        return DB::transaction(function () use ($therapy, $startOfDayUtc, $endOfDayUtc): TherapyFollowup {
            $followup = TherapyFollowup::query()
                ->where('therapy_id', $therapy->id)
                ->where('pharmacy_id', $therapy->pharmacy_id)
                ->where('entry_type', 'check')
                ->where('check_type', 'periodic')
                ->whereBetween('occurred_at', [$startOfDayUtc, $endOfDayUtc])
                ->whereNull('canceled_at')
                ->first();

            if ($followup === null) {
                $followup = TherapyFollowup::withoutGlobalScopes()->create([
                    'therapy_id' => $therapy->id,
                    'pharmacy_id' => $therapy->pharmacy_id,
                    'entry_type' => 'check',
                    'check_type' => 'periodic',
                    'occurred_at' => $startOfDayUtc,
                ]);
            }

            $questionIds = $therapy->checklistQuestions()
                ->where('is_active', true)
                ->pluck('id');

            foreach ($questionIds as $questionId) {
                TherapyChecklistAnswer::withoutGlobalScopes()->firstOrCreate([
                    'pharmacy_id' => $therapy->pharmacy_id,
                    'therapy_id' => $therapy->id,
                    'followup_id' => $followup->id,
                    'question_id' => $questionId,
                ], [
                    'answer_value' => null,
                    'answered_at' => null,
                ]);
            }

            return $followup->fresh(['checklistAnswers']);
        });
    }
}
