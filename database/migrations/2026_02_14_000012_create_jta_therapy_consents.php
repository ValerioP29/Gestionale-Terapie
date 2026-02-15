<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jta_therapy_consents', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('therapy_id');

            $table->string('signer_name', 150);
            $table->enum('signer_relation', ['patient', 'caregiver', 'familiare']);

            $table->text('consent_text');
            $table->timestamp('signed_at');

            $table->string('ip_address', 45)->nullable();
            $table->binary('signature_image')->nullable();

            $table->jsonb('scopes_json')->nullable();
            $table->string('signer_role', 20)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->index('therapy_id', 'idx_tc_therapy');
            $table->index('signer_name', 'idx_tc_signer');

            $table->foreign('therapy_id', 'fk_tc_therapy')
                ->references('id')->on('jta_therapies')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jta_therapy_consents');
    }
};
