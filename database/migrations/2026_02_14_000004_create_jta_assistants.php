<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jta_assistants', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pharma_id');

            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();

            $table->enum('preferred_contact', ['phone', 'email', 'whatsapp'])->nullable();
            $table->enum('type', ['caregiver', 'familiare'])->default('familiare');

            $table->string('relation_to_patient', 100)->nullable();
            $table->text('notes')->nullable();
            $table->jsonb('extra_info')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('pharma_id', 'idx_assistants_pharma');
            $table->index(['last_name', 'first_name'], 'idx_assistants_name');

            $table->foreign('pharma_id', 'fk_assistant_pharma')
                ->references('id')->on('jta_pharmas')
                ->cascadeOnDelete();
        });

        DB::statement("CREATE TRIGGER set_updated_at_jta_assistants
            BEFORE UPDATE ON jta_assistants
            FOR EACH ROW EXECUTE FUNCTION set_updated_at();");
    }

    public function down(): void
    {
        Schema::dropIfExists('jta_assistants');
    }
};
