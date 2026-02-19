<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('jta_therapy_reports')) {
            Schema::table('jta_therapy_reports', function (Blueprint $table): void {
                if (! Schema::hasColumn('jta_therapy_reports', 'share_token')) {
                    $table->string('share_token', 64)->nullable()->after('therapy_id');
                }

                if (! Schema::hasColumn('jta_therapy_reports', 'valid_until')) {
                    $table->timestamp('valid_until')->nullable()->after('share_token');
                }

                if (! Schema::hasColumn('jta_therapy_reports', 'pdf_path')) {
                    $table->string('pdf_path')->nullable()->after('content');
                }

                if (! Schema::hasColumn('jta_therapy_reports', 'pdf_generated_at')) {
                    $table->timestamp('pdf_generated_at')->nullable()->after('pdf_path');
                }
            });

            DB::statement("UPDATE jta_therapy_reports SET valid_until = COALESCE(valid_until, NOW() + INTERVAL '30 days')");

            if (Schema::hasColumn('jta_therapy_reports', 'pin_code')) {
                Schema::table('jta_therapy_reports', function (Blueprint $table): void {
                    $table->dropColumn('pin_code');
                });
            }

            if (Schema::hasColumn('jta_therapy_reports', 'recipients')) {
                Schema::table('jta_therapy_reports', function (Blueprint $table): void {
                    $table->dropColumn('recipients');
                });
            }

            DB::statement('ALTER TABLE jta_therapy_reports ALTER COLUMN valid_until SET NOT NULL');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uqidx_trp_share_token_not_null ON jta_therapy_reports (share_token) WHERE share_token IS NOT NULL');
        }

        if (! Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->unsignedInteger('pharmacy_id');
                $table->unsignedBigInteger('actor_user_id')->nullable();
                $table->string('action', 120);
                $table->string('subject_type', 120);
                $table->unsignedBigInteger('subject_id');
                $table->jsonb('meta')->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['pharmacy_id', 'created_at'], 'idx_audit_logs_pharmacy_created_at');
                $table->index(['action', 'created_at'], 'idx_audit_logs_action_created_at');

                $table->foreign('pharmacy_id')
                    ->references('id')
                    ->on('jta_pharmas')
                    ->cascadeOnDelete();

                $table->foreign('actor_user_id')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('jta_therapy_reports')) {
            DB::statement('DROP INDEX IF EXISTS uqidx_trp_share_token_not_null');

            Schema::table('jta_therapy_reports', function (Blueprint $table): void {
                if (! Schema::hasColumn('jta_therapy_reports', 'pin_code')) {
                    $table->string('pin_code', 255)->nullable();
                }

                if (! Schema::hasColumn('jta_therapy_reports', 'recipients')) {
                    $table->string('recipients', 255)->nullable();
                }

                if (Schema::hasColumn('jta_therapy_reports', 'pdf_generated_at')) {
                    $table->dropColumn('pdf_generated_at');
                }

                if (Schema::hasColumn('jta_therapy_reports', 'pdf_path')) {
                    $table->dropColumn('pdf_path');
                }
            });
        }

        Schema::dropIfExists('audit_logs');
    }
};
