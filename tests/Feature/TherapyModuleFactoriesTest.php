<?php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Pharmacy;
use App\Models\Therapy;
use App\Models\TherapyFollowup;
use App\Models\TherapyReminder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TherapyModuleFactoriesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('jta_therapy_followups');
        Schema::dropIfExists('jta_therapy_reminders');
        Schema::dropIfExists('jta_therapies');
        Schema::dropIfExists('jta_patients');
        Schema::dropIfExists('jta_pharmas');

        Schema::create('jta_pharmas', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('email')->nullable();
            $table->string('slug_name')->nullable();
            $table->string('slug_url')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('password')->nullable();
            $table->smallInteger('status_id')->default(0);
            $table->string('business_name')->nullable();
            $table->string('nice_name')->nullable();
            $table->string('city')->nullable();
            $table->text('address')->nullable();
            $table->string('latlng')->nullable();
            $table->text('description')->nullable();
            $table->string('logo')->nullable();
            $table->text('working_info')->nullable();
            $table->text('prompt')->nullable();
            $table->string('img_avatar')->nullable();
            $table->string('img_cover')->nullable();
            $table->string('img_bot')->nullable();
            $table->boolean('is_deleted')->default(false);
            $table->string('status')->default('active');
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
            $table->timestamp('last_access')->nullable();
        });

        Schema::create('jta_patients', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('pharmacy_id')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->date('birth_date')->nullable();
            $table->string('codice_fiscale')->nullable();
            $table->string('gender')->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('pharmacy_id')->references('id')->on('jta_pharmas')->nullOnDelete();
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
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();

            $table->foreign('pharmacy_id')->references('id')->on('jta_pharmas')->cascadeOnDelete();
            $table->foreign('patient_id')->references('id')->on('jta_patients')->cascadeOnDelete();
        });

        Schema::create('jta_therapy_reminders', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('therapy_id');
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('frequency')->default('once');
            $table->unsignedInteger('interval_value')->default(1);
            $table->unsignedSmallInteger('weekday')->nullable();
            $table->timestamp('first_due_at');
            $table->timestamp('next_due_at');
            $table->string('status')->default('active');
            $table->timestamps();

            $table->foreign('therapy_id')->references('id')->on('jta_therapies')->cascadeOnDelete();
        });

        Schema::create('jta_therapy_followups', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('therapy_id');
            $table->unsignedInteger('pharmacy_id')->nullable();
            $table->unsignedInteger('created_by')->nullable();
            $table->string('entry_type')->default('followup');
            $table->string('check_type')->nullable();
            $table->integer('risk_score')->nullable();
            $table->text('pharmacist_notes')->nullable();
            $table->text('education_notes')->nullable();
            $table->text('snapshot')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->timestamps();

            $table->foreign('therapy_id')->references('id')->on('jta_therapies')->cascadeOnDelete();
            $table->foreign('pharmacy_id')->references('id')->on('jta_pharmas')->nullOnDelete();
        });
    }

    public function test_core_therapy_factories_create_without_fk_errors(): void
    {
        $pharmacy = Pharmacy::factory()->create();
        $patient = Patient::factory()->create();
        $therapy = Therapy::factory()->create();
        $reminder = TherapyReminder::factory()->create();
        $followup = TherapyFollowup::factory()->create();

        $this->assertDatabaseHas('jta_pharmas', ['id' => $pharmacy->id]);
        $this->assertDatabaseHas('jta_patients', ['id' => $patient->id]);
        $this->assertDatabaseHas('jta_therapies', ['id' => $therapy->id]);
        $this->assertDatabaseHas('jta_therapy_reminders', ['id' => $reminder->id]);
        $this->assertDatabaseHas('jta_therapy_followups', ['id' => $followup->id]);
    }
}
