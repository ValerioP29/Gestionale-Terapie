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

            $questions = $therapy->checklistQuestions()
                ->where('is_active', true)
                ->where(function ($query): void {
                    $query->where('questionnaire_step', 'approfondito')
                        ->orWhereNull('questionnaire_step');
                })
                ->orderBy('sort_order')
                ->get();

            $followup->forceFill([
                'snapshot' => [
                    'questions' => $questions->map(fn ($question): array => [
                        'question_id' => $question->id,
                        'question_key' => $question->question_key,
                        'question_label' => $question->label,
                        'input_type' => $question->input_type,
                        'options_json' => $question->options_json,
                        'sort_order' => $question->sort_order,
                        'section' => $question->section,
                        'questionnaire_step' => $question->questionnaire_step ?? 'approfondito',
                    ])->values()->all(),
                ],
            ])->save();

            foreach ($questions as $question) {
                TherapyChecklistAnswer::withoutGlobalScopes()->firstOrCreate([
                    'pharmacy_id' => $therapy->pharmacy_id,
                    'therapy_id' => $therapy->id,
                    'followup_id' => $followup->id,
                    'question_id' => $question->id,
                ], [
                    'answer_value' => null,
                    'answer_snapshot' => [
                        'question_key' => $question->question_key,
                        'question_label' => $question->label,
                        'input_type' => $question->input_type,
                        'options_json' => $question->options_json,
                        'sort_order' => $question->sort_order,
                        'section' => $question->section,
                        'questionnaire_step' => $question->questionnaire_step ?? 'approfondito',
                    ],
                    'answered_at' => null,
                ]);
            }

            return $followup->fresh(['checklistAnswers']);
        });
    }
}
