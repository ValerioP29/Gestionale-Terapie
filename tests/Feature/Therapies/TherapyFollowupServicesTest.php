<?php

namespace Tests\Feature\Therapies;

use App\Models\Patient;
use App\Models\Pharmacy;
use App\Models\Therapy;
use App\Models\TherapyChecklistAnswer;
use App\Models\TherapyChecklistQuestion;
use App\Models\TherapyFollowup;
use App\Services\Therapies\Followups\CancelFollowupService;
use App\Services\Therapies\Followups\InitPeriodicCheckService;
use App\Services\Therapies\Followups\SaveFollowupAnswersService;
use App\Tenancy\CurrentPharmacy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TherapyFollowupServicesTest extends TestCase
{
    protected object $currentPharmacy;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('jta_therapy_checklist_answers');
        Schema::dropIfExists('jta_therapy_checklist_questions');
        Schema::dropIfExists('jta_therapy_followups');
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
            $table->string('status')->default('active');
            $table->timestamp('deleted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('jta_therapy_followups', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('pharmacy_id');
            $table->unsignedInteger('therapy_id');
            $table->string('entry_type', 20)->default('followup');
            $table->string('check_type', 20)->nullable();
            $table->timestamp('occurred_at');
            $table->integer('risk_score')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->text('pharmacist_notes')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('jta_therapy_checklist_questions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('pharmacy_id');
            $table->unsignedInteger('therapy_id');
            $table->string('condition_key')->default('generic');
            $table->string('question_key');
            $table->text('label');
            $table->string('input_type');
            $table->json('options_json')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_custom')->default(false);
            $table->timestamps();
        });

        Schema::create('jta_therapy_checklist_answers', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('pharmacy_id');
            $table->unsignedInteger('therapy_id');
            $table->unsignedInteger('followup_id');
            $table->unsignedInteger('question_id');
            $table->text('answer_value')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
            $table->unique(['followup_id', 'question_id']);
        });

        $this->currentPharmacy = new class {
            public ?int $id = null;

            public function getId(): ?int
            {
                return $this->id;
            }

            public function setId(?int $id): void
            {
                $this->id = $id;
            }
        };

        app()->instance(CurrentPharmacy::class, $this->currentPharmacy);
    }

    public function test_init_periodic_check_creates_null_answers_for_all_active_questions(): void
    {
        [$therapy] = $this->seedTherapy();

        $q1 = TherapyChecklistQuestion::withoutGlobalScopes()->create([
            'pharmacy_id' => $therapy->pharmacy_id,
            'therapy_id' => $therapy->id,
            'question_key' => 'q1',
            'label' => 'Q1',
            'input_type' => 'text',
            'is_active' => true,
        ]);
        TherapyChecklistQuestion::withoutGlobalScopes()->create([
            'pharmacy_id' => $therapy->pharmacy_id,
            'therapy_id' => $therapy->id,
            'question_key' => 'q2',
            'label' => 'Q2',
            'input_type' => 'text',
            'is_active' => true,
        ]);
        TherapyChecklistQuestion::withoutGlobalScopes()->create([
            'pharmacy_id' => $therapy->pharmacy_id,
            'therapy_id' => $therapy->id,
            'question_key' => 'q3',
            'label' => 'Q3',
            'input_type' => 'text',
            'is_active' => false,
        ]);

        $service = app(InitPeriodicCheckService::class);
        $first = $service->handle($therapy);
        $second = $service->handle($therapy);

        $this->assertSame($first->id, $second->id);
        $this->assertCount(2, $first->checklistAnswers);
        $this->assertDatabaseHas('jta_therapy_checklist_answers', [
            'followup_id' => $first->id,
            'question_id' => $q1->id,
            'answer_value' => null,
        ]);
    }

    public function test_save_followup_answers_persists_payload(): void
    {
        [$therapy] = $this->seedTherapy();

        $question = TherapyChecklistQuestion::withoutGlobalScopes()->create([
            'pharmacy_id' => $therapy->pharmacy_id,
            'therapy_id' => $therapy->id,
            'question_key' => 'q1',
            'label' => 'Q1',
            'input_type' => 'text',
            'is_active' => true,
        ]);

        $followup = TherapyFollowup::withoutGlobalScopes()->create([
            'pharmacy_id' => $therapy->pharmacy_id,
            'therapy_id' => $therapy->id,
            'entry_type' => 'check',
            'check_type' => 'periodic',
            'occurred_at' => now(),
        ]);

        app(SaveFollowupAnswersService::class)->handle($therapy, $followup, [
            'risk_score' => 33,
            'follow_up_date' => '2026-02-20',
            'pharmacist_notes' => 'note',
            'answers' => [
                $question->id => 'si',
            ],
        ]);

        $this->assertDatabaseHas('jta_therapy_followups', [
            'id' => $followup->id,
            'risk_score' => 33,
            'pharmacist_notes' => 'note',
        ]);

        $saved = TherapyChecklistAnswer::withoutGlobalScopes()->where('followup_id', $followup->id)->first();
        $this->assertSame('si', $saved?->answer_value);
        $this->assertNotNull($saved?->answered_at);
    }

    public function test_cancel_followup_sets_canceled_at(): void
    {
        [$therapy] = $this->seedTherapy();

        $followup = TherapyFollowup::withoutGlobalScopes()->create([
            'pharmacy_id' => $therapy->pharmacy_id,
            'therapy_id' => $therapy->id,
            'entry_type' => 'followup',
            'occurred_at' => now(),
        ]);

        app(CancelFollowupService::class)->handle($therapy, $followup);

        $this->assertNotNull($followup->fresh()->canceled_at);
    }

    public function test_followup_services_are_tenant_safe(): void
    {
        [$therapyOne, $therapyTwo] = $this->seedTwoTenantTherapies();

        $followupOtherTenant = TherapyFollowup::withoutGlobalScopes()->create([
            'pharmacy_id' => $therapyTwo->pharmacy_id,
            'therapy_id' => $therapyTwo->id,
            'entry_type' => 'check',
            'occurred_at' => now(),
        ]);

        $this->expectException(ValidationException::class);

        app(SaveFollowupAnswersService::class)->handle($therapyOne, $followupOtherTenant, [
            'risk_score' => 10,
        ]);
    }

    /** @return array{Therapy, Therapy} */
    private function seedTwoTenantTherapies(): array
    {
        [$therapyOne] = $this->seedTherapy('Tenant 1');
        [$therapyTwo] = $this->seedTherapy('Tenant 2');

        return [$therapyOne, $therapyTwo];
    }

    /** @return array{Therapy, Patient} */
    private function seedTherapy(string $name = 'Farmacia 1'): array
    {
        $pharmacy = Pharmacy::withoutGlobalScopes()->create(['business_name' => $name]);
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

        return [$therapy, $patient];
    }
}
