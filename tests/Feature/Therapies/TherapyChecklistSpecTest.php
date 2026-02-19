<?php

namespace Tests\Feature\Therapies;

use App\Models\Patient;
use App\Models\Pharmacy;
use App\Models\Therapy;
use App\Models\TherapyChecklistQuestion;
use App\Services\Checklist\EnsureTherapyChecklistService;
use App\Services\Therapies\CreateTherapyService;
use App\Services\Therapies\TherapyPayloadNormalizer;
use App\Tenancy\CurrentPharmacy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TherapyChecklistSpecTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('jta_therapy_checklist_questions');
        Schema::dropIfExists('jta_therapy_assistant');
        Schema::dropIfExists('jta_assistants');
        Schema::dropIfExists('jta_therapy_consents');
        Schema::dropIfExists('jta_therapy_condition_surveys');
        Schema::dropIfExists('jta_therapy_chronic_care');
        Schema::dropIfExists('jta_therapies');
        Schema::dropIfExists('jta_patients');
        Schema::dropIfExists('jta_pharmas');

        Schema::create('jta_pharmas', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('business_name')->nullable();
            $table->timestamps();
        });

        Schema::create('jta_patients', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('pharmacy_id');
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->timestamps();
        });

        Schema::create('jta_therapies', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('pharmacy_id');
            $table->unsignedInteger('patient_id');
            $table->string('therapy_title');
            $table->text('therapy_description')->nullable();
            $table->string('status')->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('jta_therapy_chronic_care', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('therapy_id');
            $table->string('primary_condition', 50);
            $table->json('care_context')->nullable();
            $table->json('doctor_info')->nullable();
            $table->json('general_anamnesis')->nullable();
            $table->json('biometric_info')->nullable();
            $table->json('detailed_intake')->nullable();
            $table->json('adherence_base')->nullable();
            $table->integer('risk_score')->nullable();
            $table->json('flags')->nullable();
            $table->text('notes_initial')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->json('consent')->nullable();
            $table->timestamps();
        });

        Schema::create('jta_therapy_consents', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('therapy_id');
            $table->string('signer_name', 150);
            $table->string('signer_relation', 20);
            $table->text('consent_text');
            $table->timestamp('signed_at');
            $table->string('ip_address', 45)->nullable();
            $table->binary('signature_image')->nullable();
            $table->json('scopes_json')->nullable();
            $table->string('signer_role', 20)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('jta_therapy_condition_surveys', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('therapy_id');
            $table->string('condition_type', 50);
            $table->string('level', 20);
            $table->json('answers')->nullable();
            $table->timestamp('compiled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('jta_assistants', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('pharma_id')->nullable();
            $table->unsignedInteger('pharmacy_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->timestamps();
        });

        Schema::create('jta_therapy_assistant', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('pharmacy_id');
            $table->unsignedInteger('therapy_id');
            $table->unsignedInteger('assistant_id');
            $table->string('role', 50)->nullable();
            $table->string('contact_channel', 30)->nullable();
            $table->json('preferences_json')->nullable();
            $table->json('consents_json')->nullable();
            $table->timestamps();
            $table->unique(['pharmacy_id', 'therapy_id', 'assistant_id'], 'uq_ta_pharmacy_therapy_assistant');
        });

        Schema::create('jta_therapy_checklist_questions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('pharmacy_id');
            $table->unsignedInteger('therapy_id');
            $table->string('condition_key');
            $table->string('question_key');
            $table->text('label');
            $table->string('input_type');
            $table->json('options_json')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_custom')->default(false);
            $table->timestamps();
            $table->unique(['therapy_id', 'question_key']);
        });

        app(CurrentPharmacy::class)->setId(1);
    }

    public function test_create_therapy_bootstraps_default_checklist_questions(): void
    {
        Pharmacy::withoutGlobalScopes()->create(['id' => 1, 'business_name' => 'A']);
        $patient = Patient::withoutGlobalScopes()->create(['pharmacy_id' => 1, 'first_name' => 'Mario']);

        $service = new CreateTherapyService(new TherapyPayloadNormalizer());

        $therapy = $service->handle([
            'patient_id' => $patient->id,
            'therapy_title' => 'Terapia diabete',
            'primary_condition' => 'diabete',
            'chronic_care' => [
                'care_context' => [['key' => 'x', 'value' => 'y']],
            ],
        ]);

        $this->assertGreaterThan(0, $therapy->checklistQuestions()->count());
        $this->assertSame('diabete', $therapy->checklistQuestions()->firstOrFail()->condition_key);
    }

    public function test_ensure_checklist_is_idempotent(): void
    {
        Pharmacy::withoutGlobalScopes()->create(['id' => 1, 'business_name' => 'A']);
        $patient = Patient::withoutGlobalScopes()->create(['pharmacy_id' => 1, 'first_name' => 'Mario']);

        $therapy = Therapy::withoutGlobalScopes()->create([
            'pharmacy_id' => 1,
            'patient_id' => $patient->id,
            'therapy_title' => 'Terapia',
            'status' => 'active',
        ]);

        $therapy->chronicCare()->create(['primary_condition' => 'ipertensione']);

        $ensureService = app(EnsureTherapyChecklistService::class);
        $ensureService->handle($therapy);
        $countAfterFirst = $therapy->checklistQuestions()->count();

        $ensureService->handle($therapy->fresh());

        $this->assertSame($countAfterFirst, $therapy->fresh()->checklistQuestions()->count());
    }

    public function test_reorder_checklist_questions_updates_sort_order(): void
    {
        $therapy = Therapy::withoutGlobalScopes()->create([
            'pharmacy_id' => 1,
            'patient_id' => Patient::withoutGlobalScopes()->create(['pharmacy_id' => 1])->id,
            'therapy_title' => 'Terapia',
            'status' => 'active',
        ]);

        $first = TherapyChecklistQuestion::withoutGlobalScopes()->create([
            'pharmacy_id' => 1,
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
            'pharmacy_id' => 1,
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

        $orderedKeys = $therapy->checklistQuestions()->orderBy('sort_order')->pluck('question_key')->all();

        $this->assertSame(['q2', 'q1'], $orderedKeys);
    }

    public function test_checklist_queries_are_tenant_isolated(): void
    {
        Pharmacy::withoutGlobalScopes()->create(['id' => 1, 'business_name' => 'A']);
        Pharmacy::withoutGlobalScopes()->create(['id' => 2, 'business_name' => 'B']);

        $patient1 = Patient::withoutGlobalScopes()->create(['pharmacy_id' => 1]);
        $patient2 = Patient::withoutGlobalScopes()->create(['pharmacy_id' => 2]);

        $therapyTenant1 = Therapy::withoutGlobalScopes()->create([
            'pharmacy_id' => 1,
            'patient_id' => $patient1->id,
            'therapy_title' => 'T1',
            'status' => 'active',
        ]);
        $therapyTenant2 = Therapy::withoutGlobalScopes()->create([
            'pharmacy_id' => 2,
            'patient_id' => $patient2->id,
            'therapy_title' => 'T2',
            'status' => 'active',
        ]);

        TherapyChecklistQuestion::withoutGlobalScopes()->create([
            'pharmacy_id' => 1,
            'therapy_id' => $therapyTenant1->id,
            'condition_key' => 'diabete',
            'question_key' => 't1_q',
            'label' => 'Tenant 1',
            'input_type' => 'boolean',
            'is_custom' => false,
        ]);
        TherapyChecklistQuestion::withoutGlobalScopes()->create([
            'pharmacy_id' => 2,
            'therapy_id' => $therapyTenant2->id,
            'condition_key' => 'diabete',
            'question_key' => 't2_q',
            'label' => 'Tenant 2',
            'input_type' => 'boolean',
            'is_custom' => false,
        ]);

        app(CurrentPharmacy::class)->setId(1);
        $tenantOneKeys = TherapyChecklistQuestion::query()->pluck('question_key')->all();

        app(CurrentPharmacy::class)->setId(2);
        $tenantTwoKeys = TherapyChecklistQuestion::query()->pluck('question_key')->all();

        $this->assertSame(['t1_q'], $tenantOneKeys);
        $this->assertSame(['t2_q'], $tenantTwoKeys);
    }
}
