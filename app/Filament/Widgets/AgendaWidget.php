<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TherapyResource;
use App\Models\TherapyFollowup;
use App\Models\TherapyReminder;
use App\Tenancy\CurrentPharmacy;
use Filament\Widgets\Widget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AgendaWidget extends Widget
{
    protected static string $view = 'filament.widgets.agenda-widget';

    protected int|string|array $columnSpan = 'full';

    protected function getViewData(): array
    {
        $tenantId = app(CurrentPharmacy::class)->getId();

        if ($tenantId === null) {
            return ['inRitardo' => collect(), 'oggi' => collect(), 'prossimi' => collect()];
        }

        $entries = $this->buildAgendaEntries($tenantId);

        $todayStart = Carbon::now('Europe/Rome')->startOfDay();
        $todayEnd = Carbon::now('Europe/Rome')->endOfDay();
        $tomorrowStart = Carbon::now('Europe/Rome')->addDay()->startOfDay();
        $upcomingEnd = Carbon::now('Europe/Rome')->addDays(7)->endOfDay();

        return [
            'inRitardo' => $entries
                ->filter(fn (array $entry): bool => $entry['scheduled_at_rome']->lt($todayStart))
                ->take(12)
                ->values(),
            'oggi' => $entries
                ->filter(fn (array $entry): bool => $entry['scheduled_at_rome']->betweenIncluded($todayStart, $todayEnd))
                ->take(12)
                ->values(),
            'prossimi' => $entries
                ->filter(fn (array $entry): bool => $entry['scheduled_at_rome']->betweenIncluded($tomorrowStart, $upcomingEnd))
                ->take(12)
                ->values(),
        ];
    }

    private function buildAgendaEntries(int $tenantId): Collection
    {
        $reminders = TherapyReminder::query()
            ->where('pharmacy_id', $tenantId)
            ->where('status', 'active')
            ->with(['therapy.patient'])
            ->get()
            ->map(function (TherapyReminder $reminder): array {
                $scheduledAtUtc = $reminder->next_due_at ?? $reminder->first_due_at;

                if ($scheduledAtUtc === null) {
                    return [];
                }

                $scheduledAtRome = Carbon::instance($scheduledAtUtc)->setTimezone('Europe/Rome');
                $patientName = trim(sprintf('%s %s', $reminder->therapy?->patient?->last_name, $reminder->therapy?->patient?->first_name));

                return [
                    'item_type' => 'Promemoria',
                    'status_label' => 'Pianificato',
                    'patient_name' => $patientName !== '' ? $patientName : 'Paziente non associato',
                    'title' => $reminder->title,
                    'subtitle' => 'Promemoria terapia',
                    'scheduled_at_rome' => $scheduledAtRome,
                    'scheduled_at_label' => $scheduledAtRome->format('d/m/Y H:i'),
                    'url' => TherapyResource::getUrl('view', ['record' => $reminder->therapy_id]),
                ];
            })
            ->filter();

        $followups = TherapyFollowup::query()
            ->where('pharmacy_id', $tenantId)
            ->whereNull('canceled_at')
            ->with(['therapy.patient'])
            ->get()
            ->map(function (TherapyFollowup $followup): array {
                $scheduledAtRome = $this->resolveFollowupDateForAgenda($followup);

                if ($scheduledAtRome === null) {
                    return [];
                }

                $patientName = trim(sprintf('%s %s', $followup->therapy?->patient?->last_name, $followup->therapy?->patient?->first_name));
                $isCheck = $followup->entry_type === 'check';

                return [
                    'item_type' => $isCheck ? 'Check periodico' : 'Follow-up manuale',
                    'status_label' => $this->statusLabel($followup),
                    'patient_name' => $patientName !== '' ? $patientName : 'Paziente non associato',
                    'title' => $isCheck ? 'Controllo periodico' : 'Contatto manuale',
                    'subtitle' => $this->shortNote($followup),
                    'scheduled_at_rome' => $scheduledAtRome,
                    'scheduled_at_label' => $scheduledAtRome->format('d/m/Y H:i'),
                    'url' => TherapyResource::getUrl('view', ['record' => $followup->therapy_id]),
                ];
            })
            ->filter();

        return $reminders
            ->merge($followups)
            ->sortBy(fn (array $entry): int => $entry['scheduled_at_rome']->getTimestamp())
            ->values();
    }

    private function resolveFollowupDateForAgenda(TherapyFollowup $followup): ?Carbon
    {
        if ($followup->follow_up_date !== null) {
            return Carbon::instance($followup->follow_up_date)->setTimezone('Europe/Rome')->startOfDay();
        }

        if ($followup->occurred_at !== null) {
            return Carbon::instance($followup->occurred_at)->setTimezone('Europe/Rome');
        }

        return null;
    }

    private function statusLabel(TherapyFollowup $followup): string
    {
        if ($followup->canceled_at !== null) {
            return 'Annullato';
        }

        if ($followup->follow_up_date !== null && Carbon::instance($followup->follow_up_date)->isFuture()) {
            return 'Pianificato';
        }

        return 'Eseguito';
    }

    private function shortNote(TherapyFollowup $followup): string
    {
        $note = trim((string) ($followup->pharmacist_notes ?? ''));

        if ($note !== '') {
            return str($note)->limit(70)->toString();
        }

        if ($followup->risk_score !== null) {
            return sprintf('Rischio clinico: %d/100', $followup->risk_score);
        }

        return 'Nessuna nota disponibile';
    }
}
