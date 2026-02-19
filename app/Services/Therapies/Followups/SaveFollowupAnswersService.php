<?php

namespace App\Services\Therapies\Followups;

use App\Models\Therapy;
use App\Models\TherapyChecklistAnswer;
use App\Models\TherapyChecklistQuestion;
use App\Models\TherapyFollowup;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class SaveFollowupAnswersService
{
    /**
     * @param array{risk_score?: int|null, follow_up_date?: string|null, pharmacist_notes?: string|null, answers?: array<int|string, mixed>} $payload
     */
    public function handle(Therapy $therapy, TherapyFollowup $followup, array $payload): TherapyFollowup
    {
        if ($followup->therapy_id !== $therapy->id || $followup->pharmacy_id !== $therapy->pharmacy_id) {
            throw ValidationException::withMessages([
                'followup_id' => 'Followup non valido per la terapia selezionata.',
            ]);
        }

        $followup->forceFill([
            'risk_score' => $payload['risk_score'] ?? null,
            'follow_up_date' => $payload['follow_up_date'] ?? null,
            'pharmacist_notes' => $payload['pharmacist_notes'] ?? null,
        ])->save();

        $answers = (array) ($payload['answers'] ?? []);

        if ($answers !== []) {
            $validQuestionIds = TherapyChecklistQuestion::query()
                ->where('therapy_id', $therapy->id)
                ->pluck('id')
                ->map(fn (int $id): string => (string) $id)
                ->all();

            foreach ($answers as $questionId => $value) {
                if (! in_array((string) $questionId, $validQuestionIds, true)) {
                    throw ValidationException::withMessages([
                        'answers' => 'Una o più domande non appartengono alla terapia.',
                    ]);
                }

                TherapyChecklistAnswer::withoutGlobalScopes()->updateOrCreate(
                    [
                        'pharmacy_id' => $therapy->pharmacy_id,
                        'therapy_id' => $therapy->id,
                        'followup_id' => $followup->id,
                        'question_id' => (int) $questionId,
                    ],
                    [
                        'answer_value' => $value === null ? null : (string) $value,
                        'answered_at' => $value === null ? null : CarbonImmutable::now(),
                    ]
                );
            }
        }

        return $followup->fresh(['checklistAnswers']);
    }
}
