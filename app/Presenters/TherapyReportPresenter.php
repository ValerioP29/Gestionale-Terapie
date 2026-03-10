<?php

namespace App\Presenters;

use App\Models\TherapyReport;
use App\Support\ConditionKeyNormalizer;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

class TherapyReportPresenter
{
    /** @var array<string, mixed> */
    private array $content;

    public function __construct(
        private readonly TherapyReport $report,
        ?array $content = null,
    ) {
        $this->content = $content ?? (array) ($report->content ?? []);
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'meta' => $this->meta(),
            'header' => $this->headerSection(),
            'patient' => $this->patientSection(),
            'therapy' => $this->therapySection(),
            'clinical' => $this->clinicalSection(),
            'checklist' => $this->checklistSection(),
            'timeline' => $this->timelineSection(),
            'checks' => $this->checksSection(),
            'reminders' => $this->reminderSection(),
            'consents' => $this->consentSection(),
        ];
    }

    /** @return array<string, string> */
    public function meta(): array
    {
        $generatedAt = $this->valueFromContent('generated_at')
            ?? $this->report->created_at?->toIso8601String();

        return [
            'report_number' => sprintf('RPT-%06d', $this->report->id),
            'generated_at_rome' => $this->formatDateTime($generatedAt),
            'valid_until_rome' => $this->formatDateTime($this->report->valid_until),
        ];
    }

    /** @return array<int, array{label:string,value:string}> */
    public function headerSection(): array
    {
        $pharmacy = $this->report->pharmacy;
        $generatedBy = (string) ($this->valueFromContent('generated_by.name') ?? 'Farmacista non disponibile');

        return [
            ['label' => 'Farmacia', 'value' => (string) ($pharmacy?->business_name ?? 'N/D')],
            ['label' => 'Città', 'value' => (string) ($pharmacy?->city ?? 'N/D')],
            ['label' => 'Indirizzo', 'value' => (string) ($pharmacy?->address ?? 'N/D')],
            ['label' => 'Farmacista', 'value' => $generatedBy],
            ['label' => 'Contatto farmacia', 'value' => (string) ($pharmacy?->phone_number ?? $pharmacy?->email ?? 'N/D')],
        ];
    }

    /** @return array<int, array{label:string,value:string}> */
    public function patientSection(): array
    {
        $patient = $this->patientData();

        return [
            ['label' => 'Nome', 'value' => (string) ($patient['first_name'] ?? 'N/D')],
            ['label' => 'Cognome', 'value' => (string) ($patient['last_name'] ?? 'N/D')],
            ['label' => 'Data di nascita', 'value' => $this->formatDate($patient['birth_date'] ?? null)],
            ['label' => 'Codice fiscale', 'value' => (string) ($patient['codice_fiscale'] ?? 'N/D')],
            ['label' => 'Telefono', 'value' => (string) ($patient['phone'] ?? 'N/D')],
            ['label' => 'Email', 'value' => (string) ($patient['email'] ?? 'N/D')],
        ];
    }

    /** @return array<int, array{label:string,value:string}> */
    public function therapySection(): array
    {
        $therapy = $this->therapyData();

        return [
            ['label' => 'Titolo terapia', 'value' => (string) ($therapy['therapy_title'] ?? 'N/D')],
            ['label' => 'Stato percorso', 'value' => $this->mapTherapyStatus($therapy['status'] ?? null)],
            ['label' => 'Data inizio', 'value' => $this->formatDate($therapy['start_date'] ?? null)],
            ['label' => 'Data fine', 'value' => $this->formatDate($therapy['end_date'] ?? null)],
            ['label' => 'Note terapia', 'value' => (string) ($therapy['therapy_description'] ?? 'Nessuna nota')],
        ];
    }

    /** @return array<int, array{label:string,value:string}> */
    public function clinicalSection(): array
    {
        $clinical = $this->latestClinicalSnapshot();
        $survey = $this->latestSurvey();

        return [
            ['label' => 'Condizione principale', 'value' => $this->conditionLabel($clinical['primary_condition'] ?? null, $clinical['custom_condition_name'] ?? null)],
            ['label' => 'Indice di rischio iniziale', 'value' => $this->formatRisk($clinical['risk_score'] ?? null)],
            ['label' => 'Prossimo follow-up suggerito', 'value' => $this->formatDate($clinical['follow_up_date'] ?? null)],
            ['label' => 'Questionario aderenza', 'value' => $this->conditionLabel($survey['condition_type'] ?? null, $clinical['custom_condition_name'] ?? null)],
            ['label' => 'Livello questionario', 'value' => (string) ($survey['level'] ?? 'N/D')],
            ['label' => 'Risposte questionario iniziale', 'value' => $this->surveyAnswersSummary($survey['answers'] ?? [])],
            ['label' => 'Note cliniche', 'value' => (string) ($clinical['notes_initial'] ?? 'Nessuna nota clinica')],
        ];
    }

    /** @return array<int, array{question:string,answer:string,answered_at:string}> */
    public function checklistSection(): array
    {
        $answers = collect((array) $this->valueFromContent('checklist_answers', []));

        if ($answers->isEmpty()) {
            return [];
        }

        return $answers
            ->sortByDesc(fn (array $row): int => Carbon::parse((string) ($row['answered_at'] ?? now()))->getTimestamp())
            ->take(8)
            ->map(function (array $row): array {
                return [
                    'question' => (string) ($row['question_label'] ?? 'Domanda checklist'),
                    'answer' => $this->formatBoolean((string) ($row['answer_value'] ?? '')),
                    'answered_at' => $this->formatDateTime($row['answered_at'] ?? null),
                ];
            })
            ->values()
            ->all();
    }

    /** @return array<int, array<string, string>> */
    public function timelineSection(): array
    {
        $followups = collect((array) $this->valueFromContent('followups', []));

        return $followups
            ->sortByDesc(fn (array $item): int => Carbon::parse((string) ($item['occurred_at'] ?? now()))->getTimestamp())
            ->take(6)
            ->map(function (array $item): array {
                return [
                    'tipo' => ($item['entry_type'] ?? null) === 'check' ? 'Check periodico' : 'Follow-up manuale',
                    'stato' => $item['canceled_at'] !== null
                        ? 'Annullato'
                        : ((($item['follow_up_date'] ?? null) !== null && Carbon::parse((string) $item['follow_up_date'])->isFuture()) ? 'Pianificato' : 'Eseguito'),
                    'data_esecuzione' => $this->formatDateTime($item['occurred_at'] ?? null),
                    'data_pianificata' => $this->formatDate($item['follow_up_date'] ?? null),
                    'rischio' => $this->formatRisk($item['risk_score'] ?? null),
                    'note' => (string) ($item['pharmacist_notes'] ?? 'Nessuna nota'),
                ];
            })
            ->values()
            ->all();
    }


    /** @return array<string, mixed> */
    public function checksSection(): array
    {
        $checks = collect((array) $this->valueFromContent('checks', []));
        $initial = $checks->first(fn (array $row): bool => ($row['check_type'] ?? null) === 'initial');
        $periodic = $checks->filter(fn (array $row): bool => ($row['check_type'] ?? null) === 'periodic')->values();

        return [
            'initial' => is_array($initial) ? $this->formatCheckRow($initial) : null,
            'periodic' => $periodic->map(fn (array $row): array => $this->formatCheckRow($row))->all(),
            'comparison' => $this->comparisonRows($initial, $periodic->all()),
        ];
    }

    /** @param array<string,mixed> $check */
    private function formatCheckRow(array $check): array
    {
        return [
            'check_type' => (string) ($check['check_type'] ?? ''),
            'occurred_at' => $this->formatDateTime($check['occurred_at'] ?? null),
            'answers' => collect((array) ($check['answers'] ?? []))->map(fn (array $answer): array => [
                'question_key' => (string) ($answer['question_key'] ?? ''),
                'question' => (string) ($answer['question_label'] ?? 'Domanda'),
                'section' => (string) ($answer['section'] ?? '-'),
                'questionnaire_step' => (string) ($answer['questionnaire_step'] ?? 'approfondito'),
                'answer' => $this->formatBoolean((string) ($answer['answer_value'] ?? '')),
                'answered_at' => $this->formatDateTime($answer['answered_at'] ?? null),
            ])->all(),
        ];
    }

    /** @param array<string,mixed>|null $initial @param array<int,array<string,mixed>> $periodic */
    private function comparisonRows(?array $initial, array $periodic): array
    {
        if (! is_array($initial)) {
            return [];
        }

        $initialMap = collect((array) ($initial['answers'] ?? []))
            ->filter(fn (array $row): bool => ($row['questionnaire_step'] ?? '') === 'approfondito')
            ->mapWithKeys(fn (array $row): array => [
                (string) ($row['question_key'] ?? $row['question_label'] ?? '') => [
                    'question' => (string) ($row['question_label'] ?? ''),
                    'answer' => (string) ($row['answer_value'] ?? ''),
                ],
            ]);

        if ($initialMap->isEmpty() || $periodic === []) {
            return [];
        }

        $latestPeriodic = collect((array) (($periodic[count($periodic) - 1]['answers'] ?? [])))
            ->filter(fn (array $row): bool => ($row['questionnaire_step'] ?? '') === 'approfondito')
            ->mapWithKeys(fn (array $row): array => [
                (string) ($row['question_key'] ?? $row['question_label'] ?? '') => (string) ($row['answer_value'] ?? ''),
            ]);

        return $initialMap->map(fn (array $initialData, string $questionKey): array => [
            'question' => (string) ($initialData['question'] ?? $questionKey),
            'initial' => $this->formatBoolean((string) ($initialData['answer'] ?? '')),
            'latest_periodic' => $this->formatBoolean((string) ($latestPeriodic->get($questionKey) ?? 'N/D')),
        ])->values()->all();
    }
    /** @return array<int, array<string, string>> */
    public function reminderSection(): array
    {
        $reminders = collect((array) $this->valueFromContent('reminders', []));

        return $reminders
            ->sortBy(fn (array $item): int => Carbon::parse((string) ($item['next_due_at'] ?? $item['first_due_at'] ?? now()))->getTimestamp())
            ->take(6)
            ->map(fn (array $item): array => [
                'titolo' => (string) ($item['title'] ?? 'Promemoria'),
                'frequenza' => $this->mapFrequency($item['frequency'] ?? null),
                'stato' => $this->mapReminderStatus($item['status'] ?? null),
                'prossima_scadenza' => $this->formatDateTime($item['next_due_at'] ?? $item['first_due_at'] ?? null),
            ])
            ->values()
            ->all();
    }

    /** @return array<int, array{label:string,value:string}> */
    public function consentSection(): array
    {
        $consent = $this->latestConsent();
        $scopes = collect((array) ($consent['scopes_json'] ?? []))->map(fn ($scope) => $this->mapConsentScope((string) $scope));

        return [
            ['label' => 'Firmatario', 'value' => (string) ($consent['signer_name'] ?? 'N/D')],
            ['label' => 'Relazione firmatario', 'value' => (string) ($consent['signer_relation'] ?? 'N/D')],
            ['label' => 'Data firma', 'value' => $this->formatDateTime($consent['signed_at'] ?? null)],
            ['label' => 'Ambiti consenso', 'value' => $scopes->isNotEmpty() ? $scopes->implode(', ') : 'N/D'],
            ['label' => 'Testo consenso', 'value' => (string) ($consent['consent_text'] ?? 'N/D')],
        ];
    }

    /** @return array<string, mixed> */
    private function patientData(): array
    {
        return (array) $this->valueFromContent('patient', $this->report->therapy?->patient?->toArray() ?? []);
    }

    /** @return array<string, mixed> */
    private function therapyData(): array
    {
        return (array) $this->valueFromContent('therapy', $this->report->therapy?->toArray() ?? []);
    }

    /** @return array<string, mixed> */
    private function latestClinicalSnapshot(): array
    {
        $fromContent = collect((array) $this->valueFromContent('chronicCare', []))->last();

        if (is_array($fromContent)) {
            return $fromContent;
        }

        return $this->report->therapy?->currentChronicCare?->toArray() ?? [];
    }

    /** @return array<string, mixed> */
    private function latestSurvey(): array
    {
        $fromContent = collect((array) $this->valueFromContent('survey', []))->last();

        if (is_array($fromContent)) {
            return $fromContent;
        }

        return $this->report->therapy?->latestSurvey?->toArray() ?? [];
    }

    /** @return array<string, mixed> */
    private function latestConsent(): array
    {
        $fromContent = collect((array) $this->valueFromContent('consents', []))->last();

        if (is_array($fromContent)) {
            return $fromContent;
        }

        return $this->report->therapy?->latestConsent?->toArray() ?? [];
    }

    private function valueFromContent(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->content, $key, $default);
    }

    private function formatDateTime(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'N/D';
        }

        try {
            return Carbon::parse((string) $value)->setTimezone('Europe/Rome')->format('d/m/Y H:i');
        } catch (\Throwable) {
            return 'N/D';
        }
    }

    private function formatDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'N/D';
        }

        try {
            return Carbon::parse((string) $value)->setTimezone('Europe/Rome')->format('d/m/Y');
        } catch (\Throwable) {
            return 'N/D';
        }
    }

    private function formatRisk(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'N/D';
        }

        return sprintf('%s/100', (string) $value);
    }

    private function formatBoolean(string $value): string
    {
        return match (strtolower(trim($value))) {
            '1', 'true', 'si', 'sì', 'yes' => 'Sì',
            '0', 'false', 'no' => 'No',
            default => $value !== '' ? $value : 'N/D',
        };
    }

    private function mapTherapyStatus(mixed $status): string
    {
        return match ((string) $status) {
            'active' => 'Attiva',
            'planned' => 'Pianificata',
            'completed' => 'Completata',
            'suspended' => 'Sospesa',
            default => 'N/D',
        };
    }

    private function mapFrequency(mixed $frequency): string
    {
        return match ((string) $frequency) {
            'one_shot' => 'Una tantum',
            'weekly' => 'Settimanale',
            'biweekly' => 'Ogni 2 settimane',
            'monthly' => 'Mensile',
            default => 'N/D',
        };
    }

    private function mapReminderStatus(mixed $status): string
    {
        return match ((string) $status) {
            'active' => 'Attivo',
            'done' => 'Eseguito',
            'paused' => 'In pausa',
            'canceled' => 'Annullato (legacy)',
            default => 'N/D',
        };
    }


    private function conditionLabel(mixed $conditionKey, mixed $customConditionName = null): string
    {
        $key = trim((string) $conditionKey);

        if ($key === '') {
            return 'N/D';
        }

        if (ConditionKeyNormalizer::isCustom($key)) {
            $custom = trim((string) $customConditionName);

            return $custom !== '' ? $custom : 'Patologia custom';
        }

        return ConditionKeyNormalizer::options()[$key] ?? $key;
    }

    private function surveyAnswersSummary(mixed $answers): string
    {
        if (! is_array($answers) || $answers === []) {
            return 'N/D';
        }

        $rows = collect($answers)
            ->filter(fn (mixed $row): bool => is_array($row))
            ->map(function (array $row): string {
                $question = trim((string) ($row['question_label'] ?? $row['question_key'] ?? 'Domanda'));
                $answer = trim((string) ($row['answer'] ?? '-'));

                return sprintf('%s: %s', $question !== '' ? $question : 'Domanda', $answer !== '' ? $answer : '-');
            })
            ->filter()
            ->values();

        return $rows->isEmpty() ? 'N/D' : $rows->implode("\n");
    }

    private function mapConsentScope(string $scope): string
    {
        return match ($scope) {
            'privacy' => 'Privacy',
            'marketing' => 'Contatto e comunicazioni',
            'profiling' => 'Uso dati anonimizzati',
            'clinical_data' => 'Follow-up clinico',
            default => $scope,
        };
    }
}
