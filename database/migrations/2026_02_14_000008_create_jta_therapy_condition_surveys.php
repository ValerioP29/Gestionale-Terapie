<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jta_therapy_condition_surveys', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('therapy_id');

            $table->string('condition_type', 50);
            $table->enum('level', ['base', 'approfondito']);
            $table->jsonb('answers')->nullable();
            $table->timestamp('compiled_at')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('therapy_id', 'idx_tcs_therapy');
            $table->index(['condition_type', 'level'], 'idx_tcs_condition');

            $table->foreign('therapy_id', 'fk_tcs_therapy')
                ->references('id')->on('jta_therapies')
                ->cascadeOnDelete();
        });

        DB::statement("CREATE TRIGGER set_updated_at_jta_therapy_condition_surveys
            BEFORE UPDATE ON jta_therapy_condition_surveys
            FOR EACH ROW EXECUTE FUNCTION set_updated_at();");
    }

    public function down(): void
    {
        Schema::dropIfExists('jta_therapy_condition_surveys');
    }
};
