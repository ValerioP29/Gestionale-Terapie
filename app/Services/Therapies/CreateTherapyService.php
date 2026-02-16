<?php

namespace App\Services\Therapies;

use App\Models\Patient;
use App\Models\Assistant;
use App\Models\Therapy;
use App\Tenancy\CurrentPharmacy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class CreateTherapyService
{
    public function __construct(private readonly TherapyPayloadNormalizer $normalizer)
    {
    }

    public function handle(array $payload): Therapy
    {
        $normalized = $this->normalizer->normalize($payload);

        return DB::transaction(function () use ($normalized): Therapy {
            $tenantId = app(CurrentPharmacy::class)->getId();

            if ($tenantId === null) {
                throw new RuntimeException('Current pharmacy not resolved');
            }

            $therapy = Therapy::create([
                'patient_id' => $this->resolvePatientId($normalized),
                'therapy_title' => $normalized['therapy_title'],
                'therapy_description' => $normalized['therapy_description'] ?? null,
                'status' => $normalized['status'] ?? 'active',
                'start_date' => $normalized['start_date'] ?? null,
                'end_date' => $normalized['end_date'] ?? null,
            ]);

            if ($therapy->status === 'suspended') {
                $therapy->end_date = Carbon::today()->toDateString();
                $therapy->save();
            }

            $this->syncChronicCare($therapy, $normalized);
            $this->storeLatestConsent($therapy, $normalized['consent'] ?? null);
            $this->storeLatestSurvey($therapy, $normalized['survey'] ?? null);

            if (isset($normalized['assistant_ids']) && is_array($normalized['assistant_ids'])) {
                $this->syncAssistants($therapy, $normalized['assistant_ids'], $tenantId);
            }

            return $therapy->fresh(['chronicCare', 'consents', 'conditionSurveys']);
        });
    }

    private function resolvePatientId(array $normalized): int
    {
        return Patient::query()->findOrFail($normalized['patient_id'])->id;
    }

    private function syncChronicCare(Therapy $therapy, array $normalized): void
    {
        if (! isset($normalized['chronic_care']) || ! is_array($normalized['chronic_care']) || $normalized['chronic_care'] === []) {
            return;
        }

        $attributes = [
            'primary_condition' => $normalized['primary_condition'] ?? 'unspecified',
            'risk_score' => $normalized['risk_score'] ?? null,
        ];

        foreach ($normalized['chronic_care'] as $block => $data) {
            $attributes[$block] = $data;
        }

        $therapy->chronicCare()->updateOrCreate([], $attributes);
    }

    private function storeLatestConsent(Therapy $therapy, ?array $consent): void
    {
        if ($consent === null) {
            return;
        }

        $therapy->consents()->create([
            'signer_name' => $consent['signer_name'],
            'signer_relation' => $consent['signer_relation'],
            'consent_text' => $consent['consent_text'],
            'signed_at' => $consent['signed_at'] ?? Carbon::now(),
            'ip_address' => $consent['ip_address'] ?? null,
            'scopes_json' => $consent['scopes_json'] ?? null,
            'signer_role' => $consent['signer_role'] ?? null,
            'created_at' => Carbon::now(),
        ]);
    }

    private function storeLatestSurvey(Therapy $therapy, ?array $survey): void
    {
        if ($survey === null) {
            return;
        }

        $therapy->conditionSurveys()->create([
            'condition_type' => $survey['condition_type'],
            'level' => $survey['level'],
            'answers' => $survey['answers'] ?? null,
            'compiled_at' => Carbon::now(),
        ]);
    }

    /** @param array<int, int|string|null> $assistantIds */
    private function syncAssistants(Therapy $therapy, array $assistantIds, int $tenantId): void
    {
        $ids = array_values(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, array_unique($assistantIds)),
            static fn (int $id): bool => $id > 0
        ));

        if ($ids === []) {
            $therapy->assistants()->sync([]);
            return;
        }

        $validIds = Assistant::query()
            ->where('pharmacy_id', $tenantId)
            ->whereIn('id', $ids)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if (count($validIds) !== count($ids)) {
            throw ValidationException::withMessages([
                'assistant_ids' => ['Invalid assistant_ids for tenant'],
            ]);
        }

        $syncData = [];
        foreach ($validIds as $assistantId) {
            $syncData[$assistantId] = ['pharmacy_id' => $tenantId];
        }

        $therapy->assistants()->sync($syncData);
    }
}
