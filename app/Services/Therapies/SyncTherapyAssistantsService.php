<?php

namespace App\Services\Therapies;

use App\Models\Assistant;
use App\Models\Therapy;
use App\Tenancy\CurrentPharmacy;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class SyncTherapyAssistantsService
{
    /** @param array<int, array<string, mixed>> $assistants */
    public function handle(Therapy $therapy, array $assistants): void
    {
        $tenantId = app(CurrentPharmacy::class)->getId();

        if ($tenantId === null) {
            throw new RuntimeException('Current pharmacy not resolved');
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
                'assistants' => ['One or more assistants are invalid for current tenant.'],
            ]);
        }

        $syncData = [];
        foreach ($assistants as $assistant) {
            $assistantId = (int) ($assistant['assistant_id'] ?? 0);

            if (! in_array($assistantId, $validIds, true)) {
                continue;
            }

            $syncData[$assistantId] = [
                'pharmacy_id' => $tenantId,
                'role' => $assistant['role'] ?? null,
                'contact_channel' => $assistant['contact_channel'] ?? null,
                'preferences_json' => $assistant['preferences_json'] ?? null,
                'consents_json' => $assistant['consents_json'] ?? null,
            ];
        }

        $therapy->assistants()->sync($syncData);
    }
}
