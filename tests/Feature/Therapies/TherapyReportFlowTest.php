<?php

namespace Tests\Feature\Therapies;

use Barryvdh\DomPDF\Facade\Pdf;
use App\Jobs\GenerateReportPdfJob;
use App\Models\Patient;
use App\Models\Pharmacy;
use App\Models\Therapy;
use App\Models\TherapyReport;
use App\Services\Audit\AuditLogger;
use App\Services\Therapies\GenerateTherapyReportService;
use App\Tenancy\CurrentPharmacy;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class TherapyReportFlowTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('jta_therapy_reports');
        Schema::dropIfExists('jta_therapy_checklist_answers');
        Schema::dropIfExists('jta_therapy_checklist_questions');
        Schema::dropIfExists('jta_therapy_followups');
        Schema::dropIfExists('jta_therapy_reminders');
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
            $table->string('primary_condition', 50)->default('hypertension');
            $table->timestamps();
        });

        Schema::create('jta_therapy_condition_surveys', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('therapy_id');
            $table->string('condition_type', 50)->default('hypertension');
            $table->string('level', 20)->default('media');
            $table->json('answers')->nullable();
            $table->timestamp('compiled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('jta_therapy_consents', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('therapy_id');
            $table->string('signer_name', 150)->default('Mario Rossi');
            $table->string('signer_relation', 20)->default('self');
            $table->text('consent_text')->default('ok');
            $table->timestamp('signed_at')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('jta_therapy_followups', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('therapy_id');
            $table->date('follow_up_date')->nullable();
            $table->timestamps();
        });

        Schema::create('jta_therapy_checklist_questions', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('therapy_id')->nullable();
            $table->unsignedInteger('pharmacy_id')->nullable();
            $table->string('label')->nullable();
            $table->timestamps();
        });

        Schema::create('jta_therapy_checklist_answers', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('followup_id');
            $table->unsignedInteger('question_id')->nullable();
            $table->string('answer_value')->nullable();
            $table->timestamp('answered_at')->nullable();
            $table->timestamps();
        });

        Schema::create('jta_therapy_reminders', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('therapy_id');
            $table->timestamp('next_due_at')->nullable();
            $table->timestamps();
        });

        Schema::create('jta_therapy_reports', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('therapy_id');
            $table->unsignedInteger('pharmacy_id');
            $table->json('content');
            $table->string('share_token', 64)->nullable();
            $table->timestamp('valid_until');
            $table->string('pdf_path')->nullable();
            $table->timestamp('pdf_generated_at')->nullable();
            $table->string('status', 20)->default('pending');
            $table->text('error_message')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedInteger('pharmacy_id');
            $table->unsignedBigInteger('actor_user_id')->nullable();
            $table->string('action', 120);
            $table->string('subject_type', 120);
            $table->unsignedBigInteger('subject_id');
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        app(CurrentPharmacy::class)->setId(1);
    }

    public function test_generate_action_creates_pending_report_and_dispatches_job(): void
    {
        Queue::fake();

        $pharmacy = Pharmacy::withoutGlobalScopes()->create(['id' => 1, 'business_name' => 'Farmacia Test']);
        $patient = Patient::withoutGlobalScopes()->create(['pharmacy_id' => $pharmacy->id, 'first_name' => 'Mario', 'last_name' => 'Rossi']);
        $therapy = Therapy::withoutGlobalScopes()->create([
            'pharmacy_id' => $pharmacy->id,
            'patient_id' => $patient->id,
            'therapy_title' => 'Terapia ipertensione',
        ]);

        $service = new GenerateTherapyReportService(app(AuditLogger::class));
        $report = $service->handle($therapy);

        $this->assertSame(TherapyReport::STATUS_PENDING, $report->status);
        $this->assertNull($report->pdf_path);

        Queue::assertPushed(GenerateReportPdfJob::class, fn (GenerateReportPdfJob $job): bool => $job->reportId === $report->id);
    }

    public function test_completed_report_is_downloadable_after_job_execution(): void
    {
        Storage::fake('public');

        $pharmacy = Pharmacy::withoutGlobalScopes()->create(['id' => 1, 'business_name' => 'Farmacia Test']);
        $patient = Patient::withoutGlobalScopes()->create(['pharmacy_id' => $pharmacy->id, 'first_name' => 'Mario', 'last_name' => 'Rossi']);
        $therapy = Therapy::withoutGlobalScopes()->create([
            'pharmacy_id' => $pharmacy->id,
            'patient_id' => $patient->id,
            'therapy_title' => 'Terapia ipertensione',
        ]);

        $report = TherapyReport::query()->create([
            'therapy_id' => $therapy->id,
            'pharmacy_id' => $pharmacy->id,
            'share_token' => 'token-download-test',
            'valid_until' => now()->addDays(30),
            'content' => ['generated_at' => now()->toIso8601String()],
            'status' => TherapyReport::STATUS_PENDING,
        ]);

        (new GenerateReportPdfJob($report->id))->handle();

        $report->refresh();

        $this->assertSame(TherapyReport::STATUS_COMPLETED, $report->status);
        $this->assertNotNull($report->pdf_path);
        Storage::disk('public')->assertExists($report->pdf_path);
    }

    public function test_failed_job_updates_report_status_with_error_message(): void
    {
        $report = TherapyReport::query()->create([
            'therapy_id' => 1,
            'pharmacy_id' => 1,
            'share_token' => 'token-failed-test',
            'valid_until' => now()->addDays(30),
            'content' => ['generated_at' => now()->toIso8601String()],
            'status' => TherapyReport::STATUS_PENDING,
        ]);

        Pdf::shouldReceive('loadView')->andThrow(new \RuntimeException('Queue worker not running'));

        try {
            (new GenerateReportPdfJob($report->id))->handle();
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Queue worker not running', $exception->getMessage());
        }

        $report->refresh();

        $this->assertSame(TherapyReport::STATUS_FAILED, $report->status);
        $this->assertSame('Queue worker not running', $report->error_message);
        $this->assertNotNull($report->failed_at);
    }
}
