<?php

namespace App\Services\Therapies;

use App\Models\Patient;
use App\Models\Therapy;
use App\Services\Audit\AuditLogger;
use App\Tenancy\CurrentPharmacy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Exceptions\CurrentPharmacyNotResolvedException;

class UpdateTherapyService
{
    /** @var array<int, string> */
    private const CHRONIC_CARE_BLOCKS = [
        'care_context',
        'doctor_info',
        'general_anamnesis',
        'biometric_info',
        'detailed_intake',
        'adherence_base',
        'flags',
    ];

    public function __construct(
        private readonly TherapyPayloadNormalizer $normalizer,
        private readonly SaveTherapyConsentsService $saveTherapyConsentsService,
        private readonly SaveTherapySurveyService $saveTherapySurveyService,
        private readonly SyncTherapyAssistantsService $syncTherapyAssistantsService,
        private readonly AuditLogger $auditLogger,
    ) {
    }

    public function handle(int $therapyId, array $payload): Therapy
    {
        $normalized = $this->normalizer->normalize($payload);

        return DB::transaction(function () use ($therapyId, $normalized): Therapy {
            $tenantId = app(CurrentPharmacy::class)->getId();

            if ($tenantId === null) {
                throw new CurrentPharmacyNotResolvedException();
            }

            $therapy = Therapy::query()
                ->where('pharmacy_id', $tenantId)
                ->findOrFail($therapyId);

            $updates = [];

            if (array_key_exists('patient_id', $normalized)) {
                $updates['patient_id'] = Patient::query()
                    ->where('pharmacy_id', $tenantId)
                    ->findOrFail($normalized['patient_id'])
                    ->id;
            }

            foreach (['therapy_title', 'therapy_description', 'status', 'start_date', 'end_date'] as $field) {
                if (array_key_exists($field, $normalized)) {
                    $updates[$field] = $normalized[$field];
                }
            }

            $nextStatus = $updates['status'] ?? $therapy->status;
            $wasSuspended = $therapy->status === 'suspended';

            if ($nextStatus === 'suspended') {
                $updates['end_date'] = Carbon::today()->toDateString();
            }

            if ($updates !== []) {
                $therapy->fill($updates);
                $therapy->save();

                if (! $wasSuspended && $nextStatus === 'suspended') {
                    $this->auditLogger->log(
                        pharmacyId: $therapy->pharmacy_id,
                        action: 'suspend_therapy',
                        subject: $therapy,
                        meta: [
                            'end_date' => $therapy->end_date?->toDateString(),
                        ],
                    );
                }
            }

            $this->syncChronicCare($therapy, $normalized);
            $this->saveTherapyConsentsService->handle($therapy, $normalized['consent'] ?? null);
            $this->saveTherapySurveyService->handle($therapy, $normalized['survey'] ?? null);

            if (array_key_exists('assistants', $normalized) || array_key_exists('assistant_ids', $normalized)) {
                $assistants = is_array($normalized['assistants'] ?? null)
                    ? $normalized['assistants']
                    : collect($normalized['assistant_ids'] ?? [])->map(static fn ($id) => ['assistant_id' => (int) $id])->all();

                $this->syncTherapyAssistantsService->handle($therapy, $assistants);
            }

            return $therapy->fresh(['patient', 'currentChronicCare', 'latestConsent', 'latestSurvey', 'assistants']);
        });
    }

    private function syncChronicCare(Therapy $therapy, array $normalized): void
    {
        $hasChronicCareBlocks = isset($normalized['chronic_care'])
            && is_array($normalized['chronic_care'])
            && $normalized['chronic_care'] !== [];

        $hasPrimaryCondition = array_key_exists('primary_condition', $normalized);

        if (! $hasChronicCareBlocks
            && ! $hasPrimaryCondition
            && ! array_key_exists('risk_score', $normalized)
            && ! array_key_exists('notes_initial', $normalized)
            && ! array_key_exists('follow_up_date', $normalized)
            && ! array_key_exists('chronic_consent', $normalized)
        ) {
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
            foreach (self::CHRONIC_CARE_BLOCKS as $block) {
                if (array_key_exists($block, $normalized['chronic_care'])) {
                    $updates[$block] = $normalized['chronic_care'][$block];
                }
            }
        }

        foreach (['primary_condition', 'risk_score', 'notes_initial', 'follow_up_date'] as $field) {
            if (array_key_exists($field, $normalized)) {
                $updates[$field] = $normalized[$field];
            }
        }

        if (array_key_exists('chronic_consent', $normalized)) {
            $updates['consent'] = $normalized['chronic_consent'];
        }

        if ($updates !== []) {
            $existing->fill($updates);
            $existing->save();
        }
    }
}
