<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('message_logs', function (Blueprint $table) {
            $table->bigIncrements('id');

            // tenancy
            $table->unsignedInteger('pharma_id');
            $table->unsignedInteger('patient_id')->nullable();

            // payload
            $table->string('to', 30);
            $table->text('body');

            // status lifecycle
            $table->string('status', 20)->default('queued'); // queued|sending|sent|failed
            $table->string('provider', 20)->default('baileys');
            $table->string('provider_message_id', 100)->nullable();

            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index(['pharma_id', 'status'], 'idx_ml_pharma_status');
            $table->index(['pharma_id', 'created_at'], 'idx_ml_pharma_created');
            $table->index('patient_id', 'idx_ml_patient');

            // FK (verso jta_pharmas e jta_patients)
            $table->foreign('pharma_id')->references('id')->on('jta_pharmas')->cascadeOnDelete();
            $table->foreign('patient_id')->references('id')->on('jta_patients')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('message_logs');
    }
};
