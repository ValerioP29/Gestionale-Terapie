<?php

namespace Tests\Feature;

use App\Models\ReminderDispatch;
use App\Models\TherapyReminder;
use App\Services\Reminders\ReminderService;
use App\Tenancy\CurrentPharmacy;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ReminderWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('jta_reminder_dispatches');
        Schema::dropIfExists('jta_therapy_reminders');
        Schema::dropIfExists('jta_therapies');

        Schema::create('jta_therapies', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedBigInteger('pharmacy_id');
            $table->string('therapy_title')->nullable();
            $table->timestamps();
            $table->timestamp('deleted_at')->nullable();
        });

        Schema::create('jta_therapy_reminders', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedBigInteger('pharmacy_id');
            $table->unsignedInteger('therapy_id');
            $table->string('title');
            $table->string('frequency');
            $table->unsignedSmallInteger('weekday')->nullable();
            $table->timestamp('first_due_at');
            $table->timestamp('next_due_at')->nullable();
            $table->timestamp('last_done_at')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('jta_reminder_dispatches', function (Blueprint $table): void {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('pharmacy_id');
            $table->unsignedInteger('reminder_id');
            $table->timestamp('due_at');
            $table->unsignedSmallInteger('attempt')->default(0);
            $table->string('outcome')->default('pending');
            $table->unsignedBigInteger('message_log_id')->nullable();
            $table->timestamps();
            $table->unique(['reminder_id', 'due_at']);
        });

        app(CurrentPharmacy::class)->setId(10);
        Carbon::setTestNow('2026-02-19 10:00:00');
    }

    public function test_mark_done_is_idempotent_for_one_shot(): void
    {
        $therapyId = DB::table('jta_therapies')->insertGetId(['pharmacy_id' => 10, 'created_at' => now(), 'updated_at' => now()]);

        $reminder = TherapyReminder::query()->create([
            'pharmacy_id' => 10,
            'therapy_id' => $therapyId,
            'title' => 'Dose singola',
            'frequency' => 'one_shot',
            'first_due_at' => now()->subDay(),
            'next_due_at' => now()->subDay(),
            'status' => 'active',
        ]);

        $service = app(ReminderService::class);

        $first = $service->markDone($reminder);
        $second = $service->markDone($reminder);

        $this->assertSame('done', $first->status);
        $this->assertNull($first->next_due_at);
        $this->assertSame($first->last_done_at?->toDateTimeString(), $second->last_done_at?->toDateTimeString());
    }

    public function test_dispatch_command_does_not_duplicate_dispatch_rows(): void
    {
        $therapyId = DB::table('jta_therapies')->insertGetId(['pharmacy_id' => 10, 'created_at' => now(), 'updated_at' => now()]);

        TherapyReminder::query()->create([
            'pharmacy_id' => 10,
            'therapy_id' => $therapyId,
            'title' => 'Reminder ricorrente',
            'frequency' => 'weekly',
            'weekday' => 4,
            'first_due_at' => now()->subDay(),
            'next_due_at' => now()->subHour(),
            'status' => 'active',
        ]);

        Artisan::call('reminders:dispatch-due');
        Artisan::call('reminders:dispatch-due');

        $this->assertSame(1, ReminderDispatch::query()->count());
    }

    public function test_dispatch_is_tenant_isolated(): void
    {
        $therapy10 = DB::table('jta_therapies')->insertGetId(['pharmacy_id' => 10, 'created_at' => now(), 'updated_at' => now()]);
        $therapy20 = DB::table('jta_therapies')->insertGetId(['pharmacy_id' => 20, 'created_at' => now(), 'updated_at' => now()]);

        TherapyReminder::withoutGlobalScopes()->create([
            'pharmacy_id' => 10,
            'therapy_id' => $therapy10,
            'title' => 'Tenant 10',
            'frequency' => 'weekly',
            'first_due_at' => now()->subDay(),
            'next_due_at' => now()->subHour(),
            'status' => 'active',
        ]);

        TherapyReminder::withoutGlobalScopes()->create([
            'pharmacy_id' => 20,
            'therapy_id' => $therapy20,
            'title' => 'Tenant 20',
            'frequency' => 'weekly',
            'first_due_at' => now()->subDay(),
            'next_due_at' => now()->subHour(),
            'status' => 'active',
        ]);

        Artisan::call('reminders:dispatch-due');

        $this->assertSame(1, ReminderDispatch::query()->count());
        $this->assertDatabaseHas('jta_reminder_dispatches', ['pharmacy_id' => 10]);
    }
}
