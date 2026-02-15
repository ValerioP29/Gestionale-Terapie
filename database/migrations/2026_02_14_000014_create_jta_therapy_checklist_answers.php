<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jta_therapy_checklist_answers', function (Blueprint $table) {
            $table->increments('id');

            $table->unsignedInteger('followup_id');
            $table->unsignedInteger('question_id');

            $table->text('answer_value')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['followup_id', 'question_id'], 'uniq_check_answer');
            $table->index('followup_id', 'idx_tca_followup');
            $table->index('question_id', 'idx_tca_question');

            $table->foreign('followup_id', 'fk_tca_followup')
                ->references('id')->on('jta_therapy_followups')
                ->cascadeOnDelete();

            $table->foreign('question_id', 'fk_tca_question')
                ->references('id')->on('jta_therapy_checklist_questions')
                ->cascadeOnDelete();
        });

        DB::statement("CREATE TRIGGER set_updated_at_jta_therapy_checklist_answers
            BEFORE UPDATE ON jta_therapy_checklist_answers
            FOR EACH ROW EXECUTE FUNCTION set_updated_at();");
    }

    public function down(): void
    {
        Schema::dropIfExists('jta_therapy_checklist_answers');
    }
};
