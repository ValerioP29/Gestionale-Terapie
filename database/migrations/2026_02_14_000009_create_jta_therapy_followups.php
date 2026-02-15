<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jta_therapy_followups', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('therapy_id');

            $table->unsignedInteger('pharmacy_id')->nullable();
            $table->unsignedInteger('created_by')->nullable();

            $table->string('entry_type', 20)->default('followup');
            $table->string('check_type', 20)->nullable();

            $table->integer('risk_score')->nullable();
            $table->text('pharmacist_notes')->nullable();
            $table->text('education_notes')->nullable();

            $table->jsonb('snapshot')->nullable();
            $table->date('follow_up_date')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('therapy_id', 'idx_tf_therapy');
            $table->index('follow_up_date', 'idx_tf_followup');
            $table->index('entry_type', 'idx_tf_entry_type');
            $table->index(['therapy_id', 'pharmacy_id'], 'idx_tf_therapy_pharmacy');

            $table->foreign('therapy_id', 'fk_tf_therapy')
                ->references('id')->on('jta_therapies')
                ->cascadeOnDelete();

            $table->foreign('pharmacy_id', 'fk_tf_pharmacy')
                ->references('id')->on('jta_pharmas')
                ->nullOnDelete();
        });

        DB::statement("CREATE TRIGGER set_updated_at_jta_therapy_followups
            BEFORE UPDATE ON jta_therapy_followups
            FOR EACH ROW EXECUTE FUNCTION set_updated_at();");
    }

    public function down(): void
    {
        Schema::dropIfExists('jta_therapy_followups');
    }
};
