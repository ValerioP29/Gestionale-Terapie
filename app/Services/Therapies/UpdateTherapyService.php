<?php

namespace App\Services\Therapies;

use App\Models\Patient;
use App\Models\Assistant;
use App\Models\Therapy;
use App\Tenancy\CurrentPharmacy;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Illuminate\Support\Facades\DB;

class UpdateTherapyService
{
    public function __construct(private readonly TherapyPayloadNormalizer $normalizer)
    {
    }

    public function handle(int $therapyId, array $payload): Therapy
    {
        $normalized = $this->normalizer->normalize($payload);

        return DB::transaction(function () use ($therapyId, $normalized): Therapy {
            $tenantId = app(CurrentPharmacy::class)->getId();

            if ($tenantId === null) {
                throw new RuntimeException('Current pharmacy not resolved');
            }

            $therapy = Therapy::query()->findOrFail($therapyId);

            $updates = [];

            if (array_key_exists('patient_id', $normalized)) {
                $updates['patient_id'] = Patient::query()->findOrFail($normalized['patient_id'])->id;
            }

            foreach (['therapy_title', 'therapy_description', 'status', 'start_date', 'end_date'] as $field) {
                if (array_key_exists($field, $normalized)) {
                    $updates[$field] = $normalized[$field];
                }
            }

            if (($updates['status'] ?? $therapy->status) === 'suspended') {
                $updates['end_date'] = Carbon::today()->toDateString();
            }

            if ($updates !== []) {
                $therapy->fill($updates);
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

    private function syncChronicCare(Therapy $therapy, array $normalized): void
    {
        $hasChronicCareBlocks = isset($normalized['chronic_care'])
            && is_array($normalized['chronic_care'])
            && $normalized['chronic_care'] !== [];

        $hasPrimaryCondition = array_key_exists('primary_condition', $normalized);

        if (! $hasChronicCareBlocks && ! $hasPrimaryCondition && ! array_key_exists('risk_score', $normalized)) {
            return;
        }

        $existing = $therapy->chronicCare()->first();

        if ($existing === null) {
            $existing = $therapy->chronicCare()->create([
                'primary_condition' => $normalized['primary_condition'] ?? 'unspecified',
            ]);
        }

        $updates = [];

        if ($hasChronicCareBlocks) {
            foreach ($normalized['chronic_care'] as $block => $data) {
                $updates[$block] = $data;
            }
        }

        if ($hasPrimaryCondition) {
            $updates['primary_condition'] = $normalized['primary_condition'];
        }

        if (array_key_exists('risk_score', $normalized)) {
            $updates['risk_score'] = $normalized['risk_score'];
        }

        if ($updates !== []) {
            $existing->fill($updates);
            $existing->save();
        }
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
