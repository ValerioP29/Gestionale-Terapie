<?php

namespace App\Services\Therapies;

use App\Models\Therapy;
use App\Models\TherapyChecklistQuestion;
use App\Models\TherapyChecklistTemplate;
use Carbon\Carbon;

class SaveTherapySurveyService
{
    public function handle(Therapy $therapy, ?array $survey): void
    {
        if ($survey === null || $survey === []) {
            return;
        }

        $conditionKey = (string) ($survey['condition_type'] ?? $therapy->currentChronicCare?->primary_condition ?? 'altro');
        $answers = is_array($survey['answers'] ?? null) ? $survey['answers'] : [];

        $therapy->conditionSurveys()->create([
            'condition_type' => $conditionKey,
            'level' => $survey['level'],
            'answers' => $answers,
            'compiled_at' => Carbon::now(),
        ]);

        $this->promoteCustomQuestionsToTemplates($therapy, $conditionKey, $answers);
    }

    /**
     * @param array<int, array<string, mixed>> $answers
     */
    private function promoteCustomQuestionsToTemplates(Therapy $therapy, string $conditionKey, array $answers): void
    {
        $customRows = collect($answers)
            ->filter(fn (mixed $row): bool => is_array($row) && trim((string) ($row['question_label'] ?? '')) !== '')
            ->values();

        if ($customRows->isEmpty()) {
            return;
        }

        $existingTemplates = TherapyChecklistTemplate::query()
            ->where('pharmacy_id', $therapy->pharmacy_id)
            ->where('condition_key', $conditionKey)
            ->get()
            ->keyBy(fn (TherapyChecklistTemplate $template): string => (string) $template->question_key);

        $nextTemplateSort = (int) ($existingTemplates->max('sort_order') ?? 0);

        $existingChecklist = TherapyChecklistQuestion::query()
            ->where('therapy_id', $therapy->id)
            ->where('pharmacy_id', $therapy->pharmacy_id)
            ->where('condition_key', $conditionKey)
            ->get()
            ->keyBy(fn (TherapyChecklistQuestion $question): string => (string) $question->question_key);

        $nextChecklistSort = (int) ($existingChecklist->max('sort_order') ?? 0);

        foreach ($customRows as $row) {
            $questionLabel = trim((string) $row['question_label']);
            $questionKey = trim((string) ($row['question_key'] ?? ''));

            if ($questionKey === '') {
                continue;
            }

            $existingTemplate = $existingTemplates->get($questionKey);
            $templateSortOrder = $existingTemplate instanceof TherapyChecklistTemplate
                ? (int) $existingTemplate->sort_order
                : ($nextTemplateSort += 10);

            $template = TherapyChecklistTemplate::query()->updateOrCreate(
                [
                    'pharmacy_id' => $therapy->pharmacy_id,
                    'condition_key' => $conditionKey,
                    'question_key' => $questionKey,
                ],
                [
                    'label' => $questionLabel,
                    'input_type' => 'text',
                    'options_json' => null,
                    'sort_order' => $templateSortOrder,
                    'is_active' => true,
                    'is_system' => false,
                ],
            );
            $existingTemplates->put($questionKey, $template);

            $existingQuestion = $existingChecklist->get($questionKey);
            $questionSortOrder = $existingQuestion instanceof TherapyChecklistQuestion
                ? (int) $existingQuestion->sort_order
                : ($nextChecklistSort += 10);

            $question = $therapy->checklistQuestions()->updateOrCreate(
                [
                    'question_key' => $questionKey,
                ],
                [
                    'pharmacy_id' => $therapy->pharmacy_id,
                    'condition_key' => $conditionKey,
                    'label' => $questionLabel,
                    'input_type' => 'text',
                    'options_json' => null,
                    'sort_order' => $questionSortOrder,
                    'is_active' => true,
                    'is_custom' => true,
                ],
            );
            $existingChecklist->put($questionKey, $question);
        }
    }
}
