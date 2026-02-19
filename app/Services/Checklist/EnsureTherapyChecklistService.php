<?php

namespace App\Services\Checklist;

use App\Domain\Checklist\ChecklistRegistry;
use App\Models\Therapy;

class EnsureTherapyChecklistService
{
    public function __construct(
        private readonly ChecklistRegistry $registry,
    ) {
    }

    public function handle(Therapy $therapy): void
    {
        if ($therapy->checklistQuestions()->exists()) {
            return;
        }

        $conditionKey = (string) ($therapy->currentChronicCare?->primary_condition ?? 'unspecified');
        $defaults = $this->registry->forCondition($conditionKey);

        if ($defaults === []) {
            return;
        }

        $payload = array_map(function (array $question) use ($therapy, $conditionKey): array {
            return [
                'pharmacy_id' => $therapy->pharmacy_id,
                'therapy_id' => $therapy->id,
                'condition_key' => $conditionKey,
                'question_key' => $question['question_key'],
                'label' => $question['label'],
                'input_type' => $question['input_type'],
                'options_json' => ($question['input_type'] ?? null) === 'select' ? ($question['options_json'] ?? null) : null,
                'is_active' => (bool) ($question['default_active'] ?? true),
                'sort_order' => (int) ($question['sort_order'] ?? 0),
                'is_custom' => false,
            ];
        }, $defaults);

        $therapy->checklistQuestions()->createMany($payload);
    }
}
