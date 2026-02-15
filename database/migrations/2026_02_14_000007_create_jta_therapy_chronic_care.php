<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jta_therapy_chronic_care', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('therapy_id');

            $table->string('primary_condition', 50);

            $table->jsonb('care_context')->nullable();
            $table->jsonb('doctor_info')->nullable();
            $table->jsonb('general_anamnesis')->nullable();
            $table->jsonb('biometric_info')->nullable();
            $table->jsonb('detailed_intake')->nullable();
            $table->jsonb('adherence_base')->nullable();

            $table->integer('risk_score')->nullable();
            $table->jsonb('flags')->nullable();

            $table->text('notes_initial')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->jsonb('consent')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('therapy_id', 'idx_tcc_therapy');
            $table->index('primary_condition', 'idx_tcc_condition');

            $table->foreign('therapy_id', 'fk_tcc_therapy')
                ->references('id')->on('jta_therapies')
                ->cascadeOnDelete();
        });

        DB::statement("CREATE TRIGGER set_updated_at_jta_therapy_chronic_care
            BEFORE UPDATE ON jta_therapy_chronic_care
            FOR EACH ROW EXECUTE FUNCTION set_updated_at();");
    }

    public function down(): void
    {
        Schema::dropIfExists('jta_therapy_chronic_care');
    }
};
