<?php

namespace App\Services\Checklist;

use App\Domain\Checklist\ChecklistRegistry;
use App\Models\Therapy;
use App\Models\TherapyChecklistTemplate;
use App\Support\ConditionKeyNormalizer;

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

        $conditionKey = ConditionKeyNormalizer::normalize((string) ($therapy->currentChronicCare?->primary_condition ?? 'altro'));

        $baseTemplates = $this->resolveTemplates($therapy->pharmacy_id, '__base__', 'base');
        $deepTemplates = $this->resolveTemplates($therapy->pharmacy_id, $conditionKey, 'approfondito');
        $templates = array_values([...$baseTemplates, ...$deepTemplates]);

        if ($templates === []) {
            return;
        }

        $payload = array_map(function (array $question) use ($therapy, $conditionKey): array {
            $step = (string) ($question['questionnaire_step'] ?? 'approfondito');

            return [
                'pharmacy_id' => $therapy->pharmacy_id,
                'therapy_id' => $therapy->id,
                'condition_key' => $step === 'base' ? '__base__' : $conditionKey,
                'questionnaire_step' => $step,
                'section' => $question['section'] ?? null,
                'question_key' => $question['question_key'],
                'label' => $question['label'],
                'input_type' => $question['input_type'],
                'options_json' => in_array(($question['input_type'] ?? null), ['select', 'multiple_choice'], true) ? ($question['options_json'] ?? null) : null,
                'is_active' => (bool) ($question['default_active'] ?? true),
                'sort_order' => (int) ($question['sort_order'] ?? 0),
                'is_custom' => false,
            ];
        }, $templates);

        $therapy->checklistQuestions()->createMany($payload);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function resolveTemplates(int $pharmacyId, string $conditionKey, string $step = 'approfondito'): array
    {
        $defaults = $step === 'approfondito' ? $this->registry->forCondition($conditionKey) : [];

        $stored = TherapyChecklistTemplate::query()
            ->where('pharmacy_id', $pharmacyId)
            ->where('condition_key', $conditionKey)
            ->where('questionnaire_step', $step)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(fn (TherapyChecklistTemplate $template): array => [
                'questionnaire_step' => (string) $template->questionnaire_step,
                'section' => $template->section,
                'question_key' => (string) $template->question_key,
                'label' => (string) $template->label,
                'input_type' => (string) $template->input_type,
                'options_json' => $template->options_json,
                'default_active' => (bool) $template->is_active,
                'sort_order' => (int) $template->sort_order,
            ])
            ->all();

        $merged = array_values(array_reduce(array_merge($defaults, $stored), function (array $carry, array $item) use ($step): array {
            if (! isset($item['questionnaire_step'])) {
                $item['questionnaire_step'] = $step;
            }

            $carry[(string) $item['question_key']] = $item;

            return $carry;
        }, []));

        usort($merged, fn (array $a, array $b): int => ((int) ($a['sort_order'] ?? 0)) <=> ((int) ($b['sort_order'] ?? 0)));

        return $merged;
    }
}
