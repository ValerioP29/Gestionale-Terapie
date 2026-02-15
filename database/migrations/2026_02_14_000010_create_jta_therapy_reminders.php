<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jta_therapy_reminders', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('therapy_id');

            $table->string('title', 255);
            $table->text('description')->nullable();

            $table->enum('frequency', ['once', 'daily', 'weekly', 'monthly'])->default('once');
            $table->unsignedInteger('interval_value')->default(1);
            $table->unsignedSmallInteger('weekday')->nullable(); // 1..7

            $table->timestamp('first_due_at');
            $table->timestamp('next_due_at');

            $table->enum('status', ['active', 'done', 'canceled'])->default('active');

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->index('therapy_id', 'idx_tr_therapy');
            $table->index(['status', 'next_due_at'], 'idx_tr_status_due');
            $table->index(['therapy_id', 'status'], 'idx_tr_therapy_status');

            $table->foreign('therapy_id', 'fk_tr_therapy')
                ->references('id')->on('jta_therapies')
                ->cascadeOnDelete();
        });

        DB::statement("CREATE TRIGGER set_updated_at_jta_therapy_reminders
            BEFORE UPDATE ON jta_therapy_reminders
            FOR EACH ROW EXECUTE FUNCTION set_updated_at();");
    }

    public function down(): void
    {
        Schema::dropIfExists('jta_therapy_reminders');
    }
};
