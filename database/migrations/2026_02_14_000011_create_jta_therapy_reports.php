<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jta_therapy_reports', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('therapy_id');
            $table->unsignedInteger('pharmacy_id');

            $table->jsonb('content');
            $table->string('share_token', 64)->nullable();
            $table->string('pin_code', 255)->nullable();
            $table->timestamp('valid_until')->nullable();
            $table->string('recipients', 255)->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('pharmacy_id', 'fk_r_pharma');
            $table->index('therapy_id', 'idx_r_therapy');
            $table->index('share_token', 'idx_r_token');

            $table->foreign('pharmacy_id', 'fk_r_pharma')
                ->references('id')->on('jta_pharmas')
                ->cascadeOnDelete();

            $table->foreign('therapy_id', 'fk_r_therapy')
                ->references('id')->on('jta_therapies')
                ->cascadeOnDelete();
        });

        DB::statement("CREATE TRIGGER set_updated_at_jta_therapy_reports
            BEFORE UPDATE ON jta_therapy_reports
            FOR EACH ROW EXECUTE FUNCTION set_updated_at();");
    }

    public function down(): void
    {
        Schema::dropIfExists('jta_therapy_reports');
    }
};
