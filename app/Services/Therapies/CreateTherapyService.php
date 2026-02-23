<?php

namespace App\Services\Therapies;

use App\Models\Patient;
use App\Services\Checklist\EnsureTherapyChecklistService;
use App\Models\Therapy;
use App\Tenancy\CurrentPharmacy;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use App\Exceptions\CurrentPharmacyNotResolvedException;
use Illuminate\Validation\ValidationException;

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
        $this->validateMinimumRequirements($normalized);

        return DB::transaction(function () use ($normalized): Therapy {
            $tenantId = app(CurrentPharmacy::class)->getId();

            if ($tenantId === null) {
                throw new CurrentPharmacyNotResolvedException();
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
            'primary_condition' => $normalized['primary_condition'] ?? 'altro',
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

    private function validateMinimumRequirements(array $normalized): void
    {
        $errors = [];

        if (! isset($normalized['patient_id']) || ! is_numeric($normalized['patient_id'])) {
            $errors['patient_id'] = 'Seleziona un paziente valido prima di salvare la terapia.';
        }

        $primaryCondition = trim((string) ($normalized['primary_condition'] ?? ''));

        if ($primaryCondition === '') {
            $errors['primary_condition'] = 'La condizione clinica principale è obbligatoria.';
        }

        $consent = $normalized['consent'] ?? null;
        $requiredScopes = ['clinical_data', 'marketing', 'profiling'];
        $scopes = collect((array) data_get($consent, 'scopes_json'))->map(fn (mixed $scope): string => (string) $scope);

        if (! is_array($consent) || $consent === []) {
            $errors['consent'] = 'Compila il consenso finale prima di completare la presa in carico.';
        } else {
            if (trim((string) data_get($consent, 'signer_name')) === '') {
                $errors['consent.signer_name'] = 'Inserisci il nominativo del firmatario.';
            }

            if (data_get($consent, 'signed_at') === null) {
                $errors['consent.signed_at'] = 'Indica data e ora della firma del consenso.';
            }

            foreach ($requiredScopes as $scope) {
                if (! $scopes->contains($scope)) {
                    $errors['consent.scopes_json'] = 'Per il percorso cronico sono obbligatori i 3 consensi minimi (follow-up, contatto, uso dati anonimizzati).';
                    break;
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
