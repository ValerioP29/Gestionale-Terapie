<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('jta_therapy_checklist_questions', function (Blueprint $table): void {
            if (! Schema::hasColumn('jta_therapy_checklist_questions', 'questionnaire_step')) {
                $table->string('questionnaire_step', 20)->default('approfondito')->after('condition_key');
            }

            if (! Schema::hasColumn('jta_therapy_checklist_questions', 'section')) {
                $table->string('section', 100)->nullable()->after('questionnaire_step');
            }
        });

        Schema::table('jta_therapy_checklist_templates', function (Blueprint $table): void {
            if (! Schema::hasColumn('jta_therapy_checklist_templates', 'questionnaire_step')) {
                $table->string('questionnaire_step', 20)->default('approfondito')->after('condition_key');
            }

            if (! Schema::hasColumn('jta_therapy_checklist_templates', 'section')) {
                $table->string('section', 100)->nullable()->after('questionnaire_step');
            }
        });

        Schema::table('jta_therapy_checklist_answers', function (Blueprint $table): void {
            if (Schema::hasColumn('jta_therapy_checklist_answers', 'question_id')) {
                DB::statement('ALTER TABLE jta_therapy_checklist_answers ALTER COLUMN question_id DROP NOT NULL');
            }

            if (! Schema::hasColumn('jta_therapy_checklist_answers', 'answer_snapshot')) {
                $table->jsonb('answer_snapshot')->nullable()->after('answer_value');
            }
        });
    }

    public function down(): void
    {
        Schema::table('jta_therapy_checklist_answers', function (Blueprint $table): void {
            if (Schema::hasColumn('jta_therapy_checklist_answers', 'answer_snapshot')) {
                $table->dropColumn('answer_snapshot');
            }
        });

        DB::statement("UPDATE jta_therapy_checklist_answers SET question_id = 0 WHERE question_id IS NULL");

        Schema::table('jta_therapy_checklist_answers', function (Blueprint $table): void {
            if (Schema::hasColumn('jta_therapy_checklist_answers', 'question_id')) {
                DB::statement('ALTER TABLE jta_therapy_checklist_answers ALTER COLUMN question_id SET NOT NULL');
            }
        });

        Schema::table('jta_therapy_checklist_templates', function (Blueprint $table): void {
            if (Schema::hasColumn('jta_therapy_checklist_templates', 'section')) {
                $table->dropColumn('section');
            }
            if (Schema::hasColumn('jta_therapy_checklist_templates', 'questionnaire_step')) {
                $table->dropColumn('questionnaire_step');
            }
        });

        Schema::table('jta_therapy_checklist_questions', function (Blueprint $table): void {
            if (Schema::hasColumn('jta_therapy_checklist_questions', 'section')) {
                $table->dropColumn('section');
            }
            if (Schema::hasColumn('jta_therapy_checklist_questions', 'questionnaire_step')) {
                $table->dropColumn('questionnaire_step');
            }
        });
    }
};

