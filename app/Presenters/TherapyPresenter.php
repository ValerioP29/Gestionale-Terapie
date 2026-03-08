<?php

namespace App\Presenters;

use App\Models\Therapy;
use App\Models\TherapyChecklistQuestion;

class TherapyPresenter
{
    public function __construct(private readonly Therapy $therapy)
    {
    }

    public function statusLabel(): string
    {
        return match ($this->therapy->status) {
            'active' => 'Attiva',
            'planned' => 'Pianificata',
            'completed' => 'Completata',
            'suspended' => 'Sospesa',
            default => (string) ($this->therapy->status ?? '-'),
        };
    }

    public function surveyAnswersReadable(): string
    {
        $answers = $this->therapy->latestSurvey?->answers;

        if (! is_array($answers) || $answers === []) {
            return '-';
        }

        $labelsByKey = TherapyChecklistQuestion::query()
            ->where('therapy_id', $this->therapy->id)
            ->pluck('label', 'question_key')
            ->map(fn (mixed $value): string => trim((string) $value))
            ->all();

        $rows = [];

        foreach ($answers as $item) {
            if (is_array($item) && array_key_exists('question_key', $item)) {
                $questionKey = (string) $item['question_key'];
                $questionLabel = trim((string) ($item['question_label'] ?? '')) ?: ($labelsByKey[$questionKey] ?? $questionKey);
                $answerValue = trim((string) ($item['answer'] ?? '-'));

                $rows[] = sprintf('%s: %s', $questionLabel, $answerValue !== '' ? $answerValue : '-');

                continue;
            }

            if (is_string($item)) {
                $rows[] = $item;
            }
        }

        return $rows === [] ? '-' : implode("\n", $rows);
    }

    public function consentScopesReadable(): string
    {
        $scopes = (array) ($this->therapy->latestConsent?->scopes_json ?? []);

        if ($scopes === []) {
            return '-';
        }

        $labels = [
            'privacy' => 'Privacy',
            'marketing' => 'Marketing',
            'profiling' => 'Profilazione',
            'clinical_data' => 'Dati clinici',
        ];

        $readable = array_map(
            fn (string $scope): string => $labels[$scope] ?? $scope,
            array_map(static fn (mixed $scope): string => (string) $scope, $scopes),
        );

        return implode(', ', $readable);
    }
}
