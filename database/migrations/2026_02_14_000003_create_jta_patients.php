<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jta_patients', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('pharmacy_id')->nullable();

            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->date('birth_date')->nullable();
            $table->string('codice_fiscale', 32)->nullable();
            $table->string('gender', 16)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->text('notes')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('pharmacy_id', 'idx_patients_pharmacy');
            $table->index(['last_name', 'first_name'], 'idx_patients_name');
            $table->index('codice_fiscale', 'idx_patients_cf');

            $table->foreign('pharmacy_id', 'fk_patients_pharmacy')
                ->references('id')->on('jta_pharmas')
                ->nullOnDelete();
        });

        DB::statement("CREATE TRIGGER set_updated_at_jta_patients
            BEFORE UPDATE ON jta_patients
            FOR EACH ROW EXECUTE FUNCTION set_updated_at();");
    }

    public function down(): void
    {
        Schema::dropIfExists('jta_patients');
    }
};
