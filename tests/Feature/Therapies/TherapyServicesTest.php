<?php

namespace Tests\Feature\Therapies;

use App\Models\Patient;
use App\Models\Pharmacy;
use App\Models\Therapy;
use App\Services\Therapies\CreateTherapyService;
use App\Services\Therapies\TherapyPayloadNormalizer;
use App\Services\Therapies\UpdateTherapyService;
use App\Tenancy\CurrentPharmacy;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TherapyServicesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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
            $table->string('email')->nullable();
            $table->string('slug_name')->nullable();
            $table->string('slug_url')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('password')->nullable();
            $table->unsignedTinyInteger('status_id')->default(1);
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

        app(CurrentPharmacy::class)->setId(1);
    }

    public function test_create_therapy_with_minimum_payload_sets_tenant_pharmacy_id(): void
    {
        Pharmacy::withoutGlobalScopes()->create(['id' => 1, 'business_name' => 'A']);
        $patient = Patient::withoutGlobalScopes()->create(['pharmacy_id' => 1, 'first_name' => 'Mario']);

        $service = new CreateTherapyService(new TherapyPayloadNormalizer());

        $therapy = $service->handle([
            'patient_id' => $patient->id,
            'therapy_title' => 'Terapia minima',
        ]);

        $this->assertSame(1, $therapy->pharmacy_id);
        $this->assertSame('active', $therapy->status);
        $this->assertDatabaseHas('jta_therapies', [
            'id' => $therapy->id,
            'pharmacy_id' => 1,
            'therapy_title' => 'Terapia minima',
        ]);
    }

    public function test_create_therapy_with_assistant_ids_stores_tenant_safe_pivot_rows(): void
    {
        Pharmacy::withoutGlobalScopes()->create(['id' => 1, 'business_name' => 'A']);
        $patient = Patient::withoutGlobalScopes()->create(['pharmacy_id' => 1, 'first_name' => 'Mario']);

        DB::table('jta_assistants')->insert([
            ['id' => 10, 'pharma_id' => 1, 'pharmacy_id' => 1, 'first_name' => 'A1', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 11, 'pharma_id' => 1, 'pharmacy_id' => 1, 'first_name' => 'A2', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $service = new CreateTherapyService(new TherapyPayloadNormalizer());

        $therapy = $service->handle([
            'patient_id' => $patient->id,
            'therapy_title' => 'Terapia con assistant',
            'assistant_ids' => [10, 11],
        ]);

        $this->assertDatabaseHas('jta_therapy_assistant', [
            'pharmacy_id' => 1,
            'therapy_id' => $therapy->id,
            'assistant_id' => 10,
        ]);

        $this->assertDatabaseHas('jta_therapy_assistant', [
            'pharmacy_id' => 1,
            'therapy_id' => $therapy->id,
            'assistant_id' => 11,
        ]);
    }

    public function test_update_therapy_to_suspended_sets_end_date_to_today(): void
    {
        Carbon::setTestNow('2026-02-16 10:00:00');

        Pharmacy::withoutGlobalScopes()->create(['id' => 1, 'business_name' => 'A']);
        $patient = Patient::withoutGlobalScopes()->create(['pharmacy_id' => 1, 'first_name' => 'Mario']);
        $therapy = Therapy::withoutGlobalScopes()->create([
            'pharmacy_id' => 1,
            'patient_id' => $patient->id,
            'therapy_title' => 'Terapia',
            'status' => 'active',
            'end_date' => null,
        ]);

        $service = new UpdateTherapyService(new TherapyPayloadNormalizer());

        $updated = $service->handle($therapy->id, ['status' => 'suspended']);

        $this->assertSame('suspended', $updated->status);
        $this->assertSame('2026-02-16', $updated->end_date?->toDateString());
    }

    public function test_create_therapy_with_suspended_status_sets_end_date_to_today(): void
    {
        Carbon::setTestNow('2026-02-20 08:00:00');

        Pharmacy::withoutGlobalScopes()->create(['id' => 1, 'business_name' => 'A']);
        $patient = Patient::withoutGlobalScopes()->create(['pharmacy_id' => 1, 'first_name' => 'Mario']);

        $service = new CreateTherapyService(new TherapyPayloadNormalizer());

        $therapy = $service->handle([
            'patient_id' => $patient->id,
            'therapy_title' => 'Sospesa',
            'status' => 'suspended',
        ]);

        $this->assertSame('suspended', $therapy->status);
        $this->assertSame('2026-02-20', $therapy->end_date?->toDateString());
    }

    public function test_update_chronic_care_replaces_known_blocks_and_ignores_unknown_blocks(): void
    {
        Pharmacy::withoutGlobalScopes()->create(['id' => 1, 'business_name' => 'A']);
        $patient = Patient::withoutGlobalScopes()->create(['pharmacy_id' => 1, 'first_name' => 'Mario']);
        $therapy = Therapy::withoutGlobalScopes()->create([
            'pharmacy_id' => 1,
            'patient_id' => $patient->id,
            'therapy_title' => 'Terapia',
            'status' => 'active',
        ]);

        $service = new UpdateTherapyService(new TherapyPayloadNormalizer());

        $service->handle($therapy->id, [
            'primary_condition' => 'diabete',
            'risk_score' => 40,
            'chronic_care' => [
                'general_anamnesis' => [
                    'allergy' => '  none ',
                    'bool_value' => 'true',
                ],
                'not_allowed' => [
                    'x' => 'y',
                ],
            ],
        ]);

        $careRow = $therapy->fresh()->chronicCare()->firstOrFail();

        $this->assertSame(['allergy' => 'none', 'bool_value' => true], $careRow->general_anamnesis);
        $this->assertNull($careRow->getAttribute('care_context'));
        $this->assertSame(40, $careRow->risk_score);

        $service->handle($therapy->id, [
            'primary_condition' => 'ipertensione',
        ]);

        $updatedCareRow = $therapy->fresh()->chronicCare()->firstOrFail();
        $this->assertSame('ipertensione', $updatedCareRow->primary_condition);
    }


    public function test_create_therapy_persists_structured_step_three_sections(): void
    {
        Pharmacy::withoutGlobalScopes()->create(['id' => 1, 'business_name' => 'A']);
        $patient = Patient::withoutGlobalScopes()->create(['pharmacy_id' => 1, 'first_name' => 'Mario']);

        $service = new CreateTherapyService(new TherapyPayloadNormalizer());

        $therapy = $service->handle([
            'patient_id' => $patient->id,
            'therapy_title' => 'Terapia cronica strutturata',
            'primary_condition' => 'diabete',
            'chronic_care' => [
                'care_context' => [
                    [
                        'question_text' => 'Paziente già seguito in terapia per questa condizione?',
                        'answer_type' => 'boolean',
                        'answer_boolean' => true,
                        'options' => null,
                    ],
                    [
                        'question_text' => 'Criticità iniziali riferite dal paziente',
                        'answer_type' => 'text',
                        'answer_text' => 'Difficoltà a rispettare gli orari',
                        'options' => null,
                    ],
                ],
                'doctor_info' => [
                    'medico_curante' => [
                        'nome' => 'Laura',
                        'cognome' => 'Bianchi',
                        'email' => 'laura.bianchi@example.test',
                        'telefono' => '3331234567',
                    ],
                    'specialista' => [
                        'nome' => 'Paolo',
                        'cognome' => 'Rossi',
                    ],
                ],
                'flags' => [
                    [
                        'question_text' => 'Priorità di intervento',
                        'answer_type' => 'single_choice',
                        'options' => ['Bassa', 'Media', 'Alta'],
                        'answer_choice' => 'Media',
                    ],
                ],
            ],
            'consent' => [
                'signer_name' => 'Mario Rossi',
                'signer_relation' => 'patient',
                'consent_text' => 'Consenso informato',
                'signed_at' => '2026-02-16 09:00:00',
                'scopes_json' => ['clinical_data', 'marketing', 'profiling'],
            ],
        ]);

        $careRow = $therapy->fresh()->chronicCare()->firstOrFail();

        $this->assertSame('diabete', $careRow->primary_condition);
        $this->assertSame('boolean', $careRow->care_context[0]['answer_type']);
        $this->assertTrue($careRow->care_context[0]['answer_boolean']);
        $this->assertSame('Laura', $careRow->doctor_info['medico_curante']['nome']);
        $this->assertSame('Media', $careRow->flags[0]['answer_choice']);
    }

    public function test_update_therapy_preserves_structured_doctor_blocks_without_key_value_shape(): void
    {
        Pharmacy::withoutGlobalScopes()->create(['id' => 1, 'business_name' => 'A']);
        $patient = Patient::withoutGlobalScopes()->create(['pharmacy_id' => 1, 'first_name' => 'Mario']);

        $therapy = Therapy::withoutGlobalScopes()->create([
            'pharmacy_id' => 1,
            'patient_id' => $patient->id,
            'therapy_title' => 'Terapia cronica',
            'status' => 'active',
        ]);

        $service = new UpdateTherapyService(new TherapyPayloadNormalizer());

        $service->handle($therapy->id, [
            'primary_condition' => 'ipertensione',
            'chronic_care' => [
                'doctor_info' => [
                    'medico_curante' => [
                        'nome' => 'Giulia',
                        'cognome' => 'Neri',
                        'email' => 'giulia.neri@example.test',
                        'telefono' => '3400000000',
                    ],
                    'specialista' => [
                        'nome' => 'Marco',
                        'cognome' => 'Verdi',
                        'telefono' => '3491111111',
                    ],
                ],
            ],
        ]);

        $careRow = $therapy->fresh()->chronicCare()->firstOrFail();

        $this->assertArrayHasKey('medico_curante', $careRow->doctor_info);
        $this->assertSame('Giulia', $careRow->doctor_info['medico_curante']['nome']);
        $this->assertArrayNotHasKey('key', $careRow->doctor_info['medico_curante']);
    }

    public function test_cross_tenant_update_fails_with_model_not_found(): void
    {
        Pharmacy::withoutGlobalScopes()->create(['id' => 1, 'business_name' => 'A']);
        Pharmacy::withoutGlobalScopes()->create(['id' => 2, 'business_name' => 'B']);

        $patientTenantTwo = Patient::withoutGlobalScopes()->create(['pharmacy_id' => 2, 'first_name' => 'Luigi']);
        $therapy = Therapy::withoutGlobalScopes()->create([
            'pharmacy_id' => 2,
            'patient_id' => $patientTenantTwo->id,
            'therapy_title' => 'Terapia B',
            'status' => 'active',
        ]);

        app(CurrentPharmacy::class)->setId(1);

        $service = new UpdateTherapyService(new TherapyPayloadNormalizer());

        $this->expectException(ModelNotFoundException::class);

        $service->handle($therapy->id, ['therapy_title' => 'Non deve aggiornare']);
    }

    public function test_create_with_cross_tenant_assistant_ids_fails(): void
    {
        Pharmacy::withoutGlobalScopes()->create(['id' => 1, 'business_name' => 'A']);
        Pharmacy::withoutGlobalScopes()->create(['id' => 2, 'business_name' => 'B']);
        $patient = Patient::withoutGlobalScopes()->create(['pharmacy_id' => 1, 'first_name' => 'Mario']);

        DB::table('jta_assistants')->insert([
            ['id' => 20, 'pharma_id' => 2, 'pharmacy_id' => 2, 'first_name' => 'B1', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $service = new CreateTherapyService(new TherapyPayloadNormalizer());

        $this->expectException(ValidationException::class);

        $service->handle([
            'patient_id' => $patient->id,
            'therapy_title' => 'Terapia',
            'assistant_ids' => [20],
        ]);
    }

    public function test_update_with_cross_tenant_assistant_ids_fails(): void
    {
        Pharmacy::withoutGlobalScopes()->create(['id' => 1, 'business_name' => 'A']);
        Pharmacy::withoutGlobalScopes()->create(['id' => 2, 'business_name' => 'B']);
        $patient = Patient::withoutGlobalScopes()->create(['pharmacy_id' => 1, 'first_name' => 'Mario']);

        $therapy = Therapy::withoutGlobalScopes()->create([
            'pharmacy_id' => 1,
            'patient_id' => $patient->id,
            'therapy_title' => 'Terapia A',
            'status' => 'active',
        ]);

        DB::table('jta_assistants')->insert([
            ['id' => 21, 'pharma_id' => 2, 'pharmacy_id' => 2, 'first_name' => 'B2', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $service = new UpdateTherapyService(new TherapyPayloadNormalizer());

        $this->expectException(ValidationException::class);

        $service->handle($therapy->id, [
            'assistant_ids' => [21],
        ]);
    }

}
