<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Pharmacy;
use App\Models\Therapy;
use App\Models\TherapyChecklistQuestion;
use App\Services\Checklist\EnsureTherapyChecklistService;
use App\Services\Therapies\CreateTherapyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TherapyChecklistQuestionsTest extends TestCase
{
    use RefreshDatabase;

    protected object $currentPharmacy;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currentPharmacy = new class {
            private ?int $id = null;

            public function getId(): ?int
            {
                return $this->id;
            }

            public function setId(?int $id): void
            {
                $this->id = $id;
            }
        };

        app()->instance(\App\Tenancy\CurrentPharmacy::class, $this->currentPharmacy);
    }

    public function test_create_therapy_bootstrap_default_checklist(): void
    {
        $pharmacy = Pharmacy::withoutGlobalScopes()->create(['business_name' => 'Farmacia 1']);
        $this->currentPharmacy->setId($pharmacy->id);

        $patient = Patient::withoutGlobalScopes()->create([
            'pharmacy_id' => $pharmacy->id,
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
        ]);

        $therapy = app(CreateTherapyService::class)->handle([
            'patient_id' => $patient->id,
            'therapy_title' => 'Terapia diabete',
            'primary_condition' => 'diabete',
            'chronic_care' => [
                'care_context' => [
                    ['key' => 'ambito', 'value' => 'domicilio'],
                ],
            ],
        ]);

        $this->assertGreaterThan(0, $therapy->checklistQuestions()->count());
        $this->assertSame('diabete', $therapy->checklistQuestions()->firstOrFail()->condition_key);
    }

    public function test_ensure_is_idempotent(): void
    {
        $pharmacy = Pharmacy::withoutGlobalScopes()->create(['business_name' => 'Farmacia 1']);
        $this->currentPharmacy->setId($pharmacy->id);

        $patient = Patient::withoutGlobalScopes()->create([
            'pharmacy_id' => $pharmacy->id,
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
        ]);

        $therapy = Therapy::withoutGlobalScopes()->create([
            'pharmacy_id' => $pharmacy->id,
            'patient_id' => $patient->id,
            'therapy_title' => 'Terapia',
            'status' => 'active',
        ]);

        $therapy->chronicCare()->create([
            'primary_condition' => 'ipertensione',
        ]);

        $service = app(EnsureTherapyChecklistService::class);
        $service->handle($therapy);
        $countAfterFirst = $therapy->checklistQuestions()->count();

        $service->handle($therapy->fresh());

        $this->assertSame($countAfterFirst, $therapy->fresh()->checklistQuestions()->count());
    }

    public function test_reorder_sort_order(): void
    {
        $pharmacy = Pharmacy::withoutGlobalScopes()->create(['business_name' => 'Farmacia 1']);
        $this->currentPharmacy->setId($pharmacy->id);

        $patient = Patient::withoutGlobalScopes()->create([
            'pharmacy_id' => $pharmacy->id,
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
        ]);

        $therapy = Therapy::withoutGlobalScopes()->create([
            'pharmacy_id' => $pharmacy->id,
            'patient_id' => $patient->id,
            'therapy_title' => 'Terapia',
            'status' => 'active',
        ]);

        $first = TherapyChecklistQuestion::withoutGlobalScopes()->create([
            'pharmacy_id' => $pharmacy->id,
            'therapy_id' => $therapy->id,
            'condition_key' => 'diabete',
            'question_key' => 'q1',
            'label' => 'Q1',
            'input_type' => 'boolean',
            'sort_order' => 10,
            'is_active' => true,
            'is_custom' => false,
        ]);

        $second = TherapyChecklistQuestion::withoutGlobalScopes()->create([
            'pharmacy_id' => $pharmacy->id,
            'therapy_id' => $therapy->id,
            'condition_key' => 'diabete',
            'question_key' => 'q2',
            'label' => 'Q2',
            'input_type' => 'boolean',
            'sort_order' => 20,
            'is_active' => true,
            'is_custom' => false,
        ]);

        $first->update(['sort_order' => 20]);
        $second->update(['sort_order' => 10]);

        $this->assertSame(['q2', 'q1'], $therapy->checklistQuestions()->pluck('question_key')->all());
    }

    public function test_tenant_isolation(): void
    {
        $pharmacyOne = Pharmacy::withoutGlobalScopes()->create(['business_name' => 'Farmacia 1']);
        $pharmacyTwo = Pharmacy::withoutGlobalScopes()->create(['business_name' => 'Farmacia 2']);

        $patientOne = Patient::withoutGlobalScopes()->create([
            'pharmacy_id' => $pharmacyOne->id,
            'first_name' => 'A',
            'last_name' => 'A',
        ]);
        $patientTwo = Patient::withoutGlobalScopes()->create([
            'pharmacy_id' => $pharmacyTwo->id,
            'first_name' => 'B',
            'last_name' => 'B',
        ]);

        $therapyOne = Therapy::withoutGlobalScopes()->create([
            'pharmacy_id' => $pharmacyOne->id,
            'patient_id' => $patientOne->id,
            'therapy_title' => 'T1',
            'status' => 'active',
        ]);
        $therapyTwo = Therapy::withoutGlobalScopes()->create([
            'pharmacy_id' => $pharmacyTwo->id,
            'patient_id' => $patientTwo->id,
            'therapy_title' => 'T2',
            'status' => 'active',
        ]);

        TherapyChecklistQuestion::withoutGlobalScopes()->create([
            'pharmacy_id' => $pharmacyOne->id,
            'therapy_id' => $therapyOne->id,
            'condition_key' => 'diabete',
            'question_key' => 't1_q',
            'label' => 'Tenant 1',
            'input_type' => 'boolean',
            'is_custom' => false,
        ]);
        TherapyChecklistQuestion::withoutGlobalScopes()->create([
            'pharmacy_id' => $pharmacyTwo->id,
            'therapy_id' => $therapyTwo->id,
            'condition_key' => 'diabete',
            'question_key' => 't2_q',
            'label' => 'Tenant 2',
            'input_type' => 'boolean',
            'is_custom' => false,
        ]);

        $this->currentPharmacy->setId($pharmacyOne->id);
        $this->assertSame(['t1_q'], TherapyChecklistQuestion::query()->pluck('question_key')->all());

        $this->currentPharmacy->setId($pharmacyTwo->id);
        $this->assertSame(['t2_q'], TherapyChecklistQuestion::query()->pluck('question_key')->all());
    }
}
