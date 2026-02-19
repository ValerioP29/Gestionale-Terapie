<?php

namespace App\Services\Therapies;

use App\Models\Patient;
use App\Services\Checklist\EnsureTherapyChecklistService;
use App\Models\Therapy;
use App\Tenancy\CurrentPharmacy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CreateTherapyService
{
    private readonly SaveTherapyConsentsService $saveTherapyConsentsService;
    private readonly SaveTherapySurveyService $saveTherapySurveyService;
    private readonly SyncTherapyAssistantsService $syncTherapyAssistantsService;
    private readonly EnsureTherapyChecklistService $ensureTherapyChecklistService;

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
        ?SaveTherapyConsentsService $saveTherapyConsentsService = null,
        ?SaveTherapySurveyService $saveTherapySurveyService = null,
        ?SyncTherapyAssistantsService $syncTherapyAssistantsService = null,
        ?EnsureTherapyChecklistService $ensureTherapyChecklistService = null,
    ) {
        $this->saveTherapyConsentsService = $saveTherapyConsentsService ?? app(SaveTherapyConsentsService::class);
        $this->saveTherapySurveyService = $saveTherapySurveyService ?? app(SaveTherapySurveyService::class);
        $this->syncTherapyAssistantsService = $syncTherapyAssistantsService ?? app(SyncTherapyAssistantsService::class);
        $this->ensureTherapyChecklistService = $ensureTherapyChecklistService ?? app(EnsureTherapyChecklistService::class);
    }

    public function handle(array $payload): Therapy
    {
        $normalized = $this->normalizer->normalize($payload);

        return DB::transaction(function () use ($normalized): Therapy {
            $tenantId = app(CurrentPharmacy::class)->getId();

            if ($tenantId === null) {
                throw new RuntimeException('Current pharmacy not resolved');
            }

            $therapy = Therapy::query()->create([
                'pharmacy_id' => $tenantId,
                'patient_id' => $this->resolvePatientId($normalized, $tenantId),
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
            $this->saveTherapyConsentsService->handle($therapy, $normalized['consent'] ?? null);
            $this->saveTherapySurveyService->handle($therapy, $normalized['survey'] ?? null);

            $assistants = is_array($normalized['assistants'] ?? null)
                ? $normalized['assistants']
                : collect($normalized['assistant_ids'] ?? [])->map(static fn ($id) => ['assistant_id' => (int) $id])->all();

            $this->syncTherapyAssistantsService->handle($therapy, $assistants);
            $this->ensureTherapyChecklistService->handle($therapy);

            return $therapy->fresh(['patient', 'currentChronicCare', 'latestConsent', 'latestSurvey', 'assistants']);
        });
    }

    private function resolvePatientId(array $normalized, int $tenantId): int
    {
        return Patient::query()
            ->where('pharmacy_id', $tenantId)
            ->findOrFail($normalized['patient_id'])
            ->id;
    }

    private function syncChronicCare(Therapy $therapy, array $normalized): void
    {
        if (! isset($normalized['chronic_care']) || ! is_array($normalized['chronic_care']) || $normalized['chronic_care'] === []) {
            return;
        }

        $attributes = [
            'primary_condition' => $normalized['primary_condition'] ?? 'unspecified',
            'risk_score' => $normalized['risk_score'] ?? null,
            'notes_initial' => $normalized['notes_initial'] ?? null,
            'follow_up_date' => $normalized['follow_up_date'] ?? null,
            'consent' => $normalized['chronic_consent'] ?? null,
        ];

        foreach (self::CHRONIC_CARE_BLOCKS as $block) {
            if (array_key_exists($block, $normalized['chronic_care'])) {
                $attributes[$block] = $normalized['chronic_care'][$block];
            }
        }

        $therapy->chronicCare()->updateOrCreate([], $attributes);
    }
}
