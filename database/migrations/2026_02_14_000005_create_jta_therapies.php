<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jta_therapies', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pharmacy_id');
            $table->unsignedInteger('patient_id');

            $table->string('therapy_title', 255);
            $table->text('therapy_description')->nullable();

            $table->enum('status', ['active', 'planned', 'completed', 'suspended'])->default('active');

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('pharmacy_id', 'idx_therapy_pharma');
            $table->index('patient_id', 'idx_therapy_patient');
            $table->index(['status', 'start_date'], 'idx_therapy_status');

            $table->foreign('patient_id', 'fk_therapy_patient')
                ->references('id')->on('jta_patients')
                ->cascadeOnDelete();

            $table->foreign('pharmacy_id', 'fk_therapy_pharma')
                ->references('id')->on('jta_pharmas')
                ->cascadeOnDelete();
        });

        DB::statement("CREATE TRIGGER set_updated_at_jta_therapies
            BEFORE UPDATE ON jta_therapies
            FOR EACH ROW EXECUTE FUNCTION set_updated_at();");
    }

    public function down(): void
    {
        Schema::dropIfExists('jta_therapies');
    }
};
