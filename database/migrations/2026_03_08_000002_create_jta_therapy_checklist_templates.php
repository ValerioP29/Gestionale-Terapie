<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jta_therapy_checklist_templates', function (Blueprint $table): void {
            $table->increments('id');
            $table->unsignedInteger('pharmacy_id');
            $table->string('condition_key', 100);
            $table->string('question_key', 191);
            $table->text('label');
            $table->string('input_type', 20)->default('text');
            $table->jsonb('options_json')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->unique(['pharmacy_id', 'condition_key', 'question_key'], 'uq_tct_scope_key');
            $table->index(['pharmacy_id', 'condition_key'], 'idx_tct_scope');

            $table->foreign('pharmacy_id', 'fk_tct_pharmacy')
                ->references('id')->on('jta_pharmas')
                ->cascadeOnDelete();
        });

        DB::statement("CREATE TRIGGER set_updated_at_jta_therapy_checklist_templates
            BEFORE UPDATE ON jta_therapy_checklist_templates
            FOR EACH ROW EXECUTE FUNCTION set_updated_at();");
    }

    public function down(): void
    {
        Schema::dropIfExists('jta_therapy_checklist_templates');
    }
};
