<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('jta_therapy_reports')) {
            return;
        }

        Schema::table('jta_therapy_reports', function (Blueprint $table): void {
            if (! Schema::hasColumn('jta_therapy_reports', 'status')) {
                $table->string('status', 20)->default('pending')->after('valid_until');
            }

            if (! Schema::hasColumn('jta_therapy_reports', 'error_message')) {
                $table->text('error_message')->nullable()->after('status');
            }

            if (! Schema::hasColumn('jta_therapy_reports', 'failed_at')) {
                $table->timestamp('failed_at')->nullable()->after('error_message');
            }
        });

        DB::statement("UPDATE jta_therapy_reports SET status = CASE WHEN pdf_path IS NULL THEN 'pending' ELSE 'completed' END WHERE status IS NULL OR status = ''");
        DB::statement('CREATE INDEX IF NOT EXISTS idx_trp_status ON jta_therapy_reports (status)');
    }

    public function down(): void
    {
        if (! Schema::hasTable('jta_therapy_reports')) {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS idx_trp_status');

        Schema::table('jta_therapy_reports', function (Blueprint $table): void {
            if (Schema::hasColumn('jta_therapy_reports', 'failed_at')) {
                $table->dropColumn('failed_at');
            }

            if (Schema::hasColumn('jta_therapy_reports', 'error_message')) {
                $table->dropColumn('error_message');
            }

            if (Schema::hasColumn('jta_therapy_reports', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
