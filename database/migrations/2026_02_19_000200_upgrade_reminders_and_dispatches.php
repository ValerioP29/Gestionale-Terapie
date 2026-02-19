<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jta_therapy_reminders', function (Blueprint $table): void {
            if (! Schema::hasColumn('jta_therapy_reminders', 'pharmacy_id')) {
                $table->unsignedInteger('pharmacy_id')->nullable()->after('id');
            }
            if (! Schema::hasColumn('jta_therapy_reminders', 'last_done_at')) {
                $table->timestamp('last_done_at')->nullable()->after('next_due_at');
            }
        });

        DB::table('jta_therapy_reminders')
            ->join('jta_therapies', 'jta_therapies.id', '=', 'jta_therapy_reminders.therapy_id')
            ->whereNull('jta_therapy_reminders.pharmacy_id')
            ->update(['jta_therapy_reminders.pharmacy_id' => DB::raw('jta_therapies.pharmacy_id')]);

        DB::table('jta_therapy_reminders')->where('frequency', 'once')->update(['frequency' => 'one_shot']);
        DB::table('jta_therapy_reminders')->where('frequency', 'daily')->update(['frequency' => 'weekly']);
        DB::table('jta_therapy_reminders')->where('status', 'canceled')->update(['status' => 'paused']);

        DB::statement('ALTER TABLE jta_therapy_reminders ALTER COLUMN pharmacy_id TYPE integer USING pharmacy_id::integer');
        DB::statement('ALTER TABLE jta_therapy_reminders ALTER COLUMN pharmacy_id SET NOT NULL');

        if (Schema::hasColumn('jta_therapy_reminders', 'description')) {
            Schema::table('jta_therapy_reminders', function (Blueprint $table): void {
                $table->dropColumn('description');
            });
        }

        if (Schema::hasColumn('jta_therapy_reminders', 'interval_value')) {
            Schema::table('jta_therapy_reminders', function (Blueprint $table): void {
                $table->dropColumn('interval_value');
            });
        }

        Schema::table('jta_therapy_reminders', function (Blueprint $table): void {
            $table->index('pharmacy_id', 'idx_tr_pharmacy');
            $table->index(['pharmacy_id', 'status', 'next_due_at'], 'idx_tr_pharmacy_status_due');
        });

        if (! Schema::hasTable('jta_reminder_dispatches')) {
            Schema::create('jta_reminder_dispatches', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('pharmacy_id');
                $table->unsignedInteger('reminder_id');
                $table->timestamp('due_at');
                $table->unsignedSmallInteger('attempt')->default(0);
                $table->enum('outcome', ['pending', 'sent', 'failed'])->default('pending');
                $table->unsignedBigInteger('message_log_id')->nullable();
                $table->timestamps();

                $table->unique(['reminder_id', 'due_at'], 'uq_reminder_due');
                $table->index(['pharmacy_id', 'outcome'], 'idx_rd_pharmacy_outcome');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('jta_reminder_dispatches');
    }
};
