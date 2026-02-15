<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_dispatches', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedInteger('reminder_id');
            $table->unsignedBigInteger('message_log_id')->nullable();

            $table->timestamp('scheduled_for');
            $table->timestamp('dispatched_at')->nullable();

            $table->string('status', 20)->default('scheduled'); 
            // scheduled | dispatched | failed | skipped

            $table->text('error')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index(['reminder_id', 'status'], 'idx_rd_reminder_status');
            $table->index('scheduled_for', 'idx_rd_scheduled');

            $table->foreign('reminder_id')
                ->references('id')
                ->on('jta_therapy_reminders')
                ->cascadeOnDelete();

            $table->foreign('message_log_id')
                ->references('id')
                ->on('message_logs')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_dispatches');
    }
};
