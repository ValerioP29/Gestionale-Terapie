<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jta_therapy_checklist_questions', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('therapy_id');
            $table->unsignedInteger('pharmacy_id');

            $table->string('condition_key', 100);
            $table->string('question_key', 191);

            $table->text('label');
            $table->string('input_type', 20)->default('text');
            $table->jsonb('options_json')->nullable();

            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_custom')->default(false);

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['therapy_id', 'question_key'], 'uniq_tcq_therapy_key');
            $table->index('therapy_id', 'idx_tcq_therapy');
            $table->index('pharmacy_id', 'idx_tcq_pharmacy');
            $table->index('condition_key', 'idx_tcq_condition');

            $table->foreign('therapy_id', 'fk_tcq_therapy')
                ->references('id')->on('jta_therapies')
                ->cascadeOnDelete();

            $table->foreign('pharmacy_id', 'fk_tcq_pharmacy')
                ->references('id')->on('jta_pharmas')
                ->cascadeOnDelete();
        });

        DB::statement("CREATE TRIGGER set_updated_at_jta_therapy_checklist_questions
            BEFORE UPDATE ON jta_therapy_checklist_questions
            FOR EACH ROW EXECUTE FUNCTION set_updated_at();");
    }

    public function down(): void
    {
        Schema::dropIfExists('jta_therapy_checklist_questions');
    }
};
