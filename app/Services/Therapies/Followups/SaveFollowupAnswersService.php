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
    public function __construct(private readonly ChecklistAnswerValueValidator $valueValidator)
    {
    }

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
            $questions = TherapyChecklistQuestion::query()
                ->where('therapy_id', $therapy->id)
                ->get()
                ->keyBy(fn (TherapyChecklistQuestion $question): string => (string) $question->id);

            foreach ($answers as $questionId => $value) {
                $question = $questions->get((string) $questionId);

                if ($question === null) {
                    throw ValidationException::withMessages([
                        'answers' => 'Una o più domande non appartengono alla terapia.',
                    ]);
                }

                $normalizedValue = $this->valueValidator->validateAndNormalize(
                    value: $value,
                    inputType: $question->input_type,
                    options: $question->options_json,
                );

                TherapyChecklistAnswer::withoutGlobalScopes()->updateOrCreate(
                    [
                        'pharmacy_id' => $therapy->pharmacy_id,
                        'therapy_id' => $therapy->id,
                        'followup_id' => $followup->id,
                        'question_id' => (int) $questionId,
                    ],
                    [
                        'answer_value' => $normalizedValue === null
                            ? null
                            : $this->serializeAnswerValue($normalizedValue, $question->input_type),
                        'answered_at' => $normalizedValue === null ? null : CarbonImmutable::now('UTC'),
                    ]
                );
            }
        }

        return $followup->fresh(['checklistAnswers']);
    }

    private function serializeAnswerValue(mixed $value, string $inputType): string
    {
        if ($inputType === 'boolean') {
            return $value ? '1' : '0';
        }

        return (string) $value;
    }
}
