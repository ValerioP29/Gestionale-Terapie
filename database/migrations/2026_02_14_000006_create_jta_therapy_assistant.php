<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jta_therapy_assistant', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('therapy_id');
            $table->unsignedInteger('assistant_id');

            $table->enum('role', ['caregiver', 'familiare']);
            $table->jsonb('preferences_json')->nullable();
            $table->jsonb('consents_json')->nullable();
            $table->string('contact_channel', 20)->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->unique(['therapy_id', 'assistant_id'], 'uq_ta_unique');
            $table->index('assistant_id', 'fk_ta_assistant');

            $table->foreign('assistant_id', 'fk_ta_assistant')
                ->references('id')->on('jta_assistants')
                ->cascadeOnDelete();

            $table->foreign('therapy_id', 'fk_ta_therapy')
                ->references('id')->on('jta_therapies')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jta_therapy_assistant');
    }
};
