<?php

namespace App\Services\Therapies;

use App\Models\Assistant;
use App\Models\Therapy;
use App\Tenancy\CurrentPharmacy;
use Illuminate\Validation\ValidationException;
use App\Exceptions\CurrentPharmacyNotResolvedException;

class SyncTherapyAssistantsService
{
    /** @param array<int, array<string, mixed>> $assistants */
    public function handle(Therapy $therapy, array $assistants): void
    {
        $tenantId = app(CurrentPharmacy::class)->getId();

        if ($tenantId === null) {
            throw new CurrentPharmacyNotResolvedException();
        }

        if ($assistants === []) {
            $therapy->assistants()->sync([]);

            return;
        }

        $assistantIds = collect($assistants)
            ->pluck('assistant_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $validIds = Assistant::query()
            ->where('pharma_id', $tenantId)
            ->whereIn('id', $assistantIds)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if (count($validIds) !== count($assistantIds)) {
            throw ValidationException::withMessages([
                'assistants' => ['Uno o più assistenti non sono validi per la farmacia corrente.'],
            ]);
        }

        $syncData = [];
        foreach ($assistants as $assistant) {
            $assistantId = (int) ($assistant['assistant_id'] ?? 0);

            if (! in_array($assistantId, $validIds, true)) {
                continue;
            }

            $preferences = [
                'contatto_telefonico' => ($assistant['pref_contact_phone'] ?? null) === 'si',
                'contatto_email' => ($assistant['pref_contact_email'] ?? null) === 'si',
                'contatto_sms_whatsapp' => ($assistant['pref_contact_sms_whatsapp'] ?? null) === 'si',
            ];

            $consents = [
                'comunicazioni_terapia' => ($assistant['consent_therapy_contact'] ?? null) === 'si',
                'trattamento_dati_terapia' => ($assistant['consent_data_processing'] ?? null) === 'si',
            ];

            $syncData[$assistantId] = [
                'pharmacy_id' => $tenantId,
                'role' => $assistant['role'] ?? null,
                'contact_channel' => self::resolvePreferredContactChannel($preferences),
                'preferences_json' => $preferences,
                'consents_json' => $consents,
            ];
        }

        $therapy->assistants()->sync($syncData);
    }

    /** @param array<string, bool> $preferences */
    private static function resolvePreferredContactChannel(array $preferences): ?string
    {
        if (($preferences['contatto_telefonico'] ?? false) === true) {
            return 'phone';
        }

        if (($preferences['contatto_email'] ?? false) === true) {
            return 'email';
        }

        if (($preferences['contatto_sms_whatsapp'] ?? false) === true) {
            return 'whatsapp';
        }

        return null;
    }
}

