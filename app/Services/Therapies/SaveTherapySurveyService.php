<?php

namespace App\Services\Therapies;

use App\Models\Therapy;
use App\Models\TherapyChecklistTemplate;
use App\Models\TherapyFollowup;
use Carbon\Carbon;

class SaveTherapySurveyService
{
    public function handle(Therapy $therapy, ?array $survey): void
    {
        if ($survey === null || $survey === []) {
            return;
        }

        $conditionKey = (string) ($survey['condition_type'] ?? $therapy->currentChronicCare?->primary_condition ?? 'altro');

        $baseQuestions = $this->normalizeQuestionRows((array) ($survey['base_questions'] ?? []), 'base');
        $deepQuestions = $this->normalizeQuestionRows((array) ($survey['approfondito_questions'] ?? []), 'approfondito');

        $therapy->conditionSurveys()->create([
            'condition_type' => $conditionKey,
            'level' => 'approfondito',
            'answers' => [
                'base_questions' => $baseQuestions,
                'approfondito_questions' => $deepQuestions,
            ],
            'compiled_at' => Carbon::now(),
        ]);

        $this->persistTemplatesAndChecklist($therapy, '__base__', 'base', $baseQuestions);
        $this->persistTemplatesAndChecklist($therapy, $conditionKey, 'approfondito', $deepQuestions);

        $this->ensureInitialCheckSnapshot($therapy, $baseQuestions, $deepQuestions);
    }

    /** @param array<int, array<string,mixed>> $rows */
    private function normalizeQuestionRows(array $rows, string $step): array
    {
        return collect($rows)
            ->filter(fn (mixed $row): bool => is_array($row) && trim((string) ($row['question_label'] ?? '')) !== '')
            ->values()
            ->map(function (array $row, int $index) use ($step): array {
                $inputType = (string) ($row['input_type'] ?? 'text');
                $rawKey = trim((string) ($row['question_key'] ?? ''));

                if ($rawKey === '') {
                    $rawKey = sprintf('%s_%s_%03d', $step, md5((string) $row['question_label']), $index + 1);
                }

                return [
                    'questionnaire_step' => $step,
                    'section' => trim((string) ($row['section'] ?? '')) ?: null,
                    'question_key' => $this->canonicalQuestionKey($step, $rawKey),
                    'question_label' => trim((string) $row['question_label']),
                    'input_type' => $this->mapInputType($inputType),
                    'options_json' => $this->normalizeOptions($row['options_json'] ?? null),
                    'answer' => $this->normalizeAnswer($row['answer'] ?? null, $inputType),
                    'answer_detail' => trim((string) ($row['answer_detail'] ?? '')) ?: null,
                    'sort_order' => is_numeric($row['sort_order'] ?? null) ? (int) $row['sort_order'] : (($index + 1) * 10),
                ];
            })
            ->all();
    }

    private function normalizeAnswer(mixed $answer, string $inputType): mixed
    {
        if ($answer === null || $answer === '') {
            return null;
        }

        if ($inputType === 'multiple_choice') {
            return collect((array) $answer)->map(fn (mixed $v): string => trim((string) $v))->filter()->values()->all();
        }

        if ($inputType === 'number' && is_numeric($answer)) {
            return (float) $answer;
        }

        return is_string($answer) ? trim($answer) : $answer;
    }

    private function persistTemplatesAndChecklist(Therapy $therapy, string $conditionKey, string $step, array $questions): void
    {
        foreach ($questions as $question) {
            TherapyChecklistTemplate::query()->updateOrCreate(
                [
                    'pharmacy_id' => $therapy->pharmacy_id,
                    'condition_key' => $conditionKey,
                    'questionnaire_step' => $step,
                    'question_key' => $question['question_key'],
                ],
                [
                    'section' => $question['section'],
                    'label' => $question['question_label'],
                    'input_type' => $question['input_type'],
                    'options_json' => $question['options_json'],
                    'sort_order' => $question['sort_order'],
                    'is_active' => true,
                    'is_system' => false,
                ],
            );

            $therapy->checklistQuestions()->updateOrCreate(
                [
                    'question_key' => $question['question_key'],
                ],
                [
                    'pharmacy_id' => $therapy->pharmacy_id,
                    'condition_key' => $conditionKey,
                    'questionnaire_step' => $step,
                    'section' => $question['section'],
                    'label' => $question['question_label'],
                    'input_type' => $question['input_type'],
                    'options_json' => $question['options_json'],
                    'sort_order' => $question['sort_order'],
                    'is_active' => true,
                    'is_custom' => true,
                ],
            );
        }
    }

    private function ensureInitialCheckSnapshot(Therapy $therapy, array $baseQuestions, array $deepQuestions): void
    {
        $initial = TherapyFollowup::query()->firstOrCreate(
            [
                'therapy_id' => $therapy->id,
                'pharmacy_id' => $therapy->pharmacy_id,
                'entry_type' => 'check',
                'check_type' => 'initial',
            ],
            [
                'occurred_at' => now('UTC'),
                'snapshot' => ['questions' => []],
            ],
        );

        $initial->forceFill([
            'snapshot' => [
                'questions' => array_values([...$baseQuestions, ...$deepQuestions]),
            ],
        ])->save();
    }

    private function mapInputType(string $inputType): string
    {
        return match ($inputType) {
            'testo_breve' => 'text',
            'testo_lungo' => 'text',
            'si_no' => 'boolean',
            'scelta_singola' => 'select',
            'scelta_multipla', 'multiple_choice' => 'multiple_choice',
            default => $inputType,
        };
    }

    /** @return array<int,string>|null */
    private function normalizeOptions(mixed $options): ?array
    {
        $values = collect((array) $options)->map(fn (mixed $v): string => trim((string) $v))->filter()->values()->all();

        return $values === [] ? null : $values;
    }

    private function canonicalQuestionKey(string $step, string $questionKey): string
    {
        return str_starts_with($questionKey, $step.':')
            ? $questionKey
            : sprintf('%s:%s', $step, $questionKey);
    }
}
