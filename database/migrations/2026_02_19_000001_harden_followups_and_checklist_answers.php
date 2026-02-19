<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('jta_therapy_followups', function (Blueprint $table): void {
            if (! Schema::hasColumn('jta_therapy_followups', 'occurred_at')) {
                $table->timestamp('occurred_at')->nullable()->after('check_type');
            }

            if (! Schema::hasColumn('jta_therapy_followups', 'canceled_at')) {
                $table->timestamp('canceled_at')->nullable()->after('follow_up_date');
            }
        });

        DB::statement('UPDATE jta_therapy_followups SET occurred_at = COALESCE(occurred_at, created_at, NOW())');
        DB::statement('ALTER TABLE jta_therapy_followups ALTER COLUMN occurred_at SET NOT NULL');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_tf_pharmacy_id ON jta_therapy_followups (pharmacy_id)');

        Schema::table('jta_therapy_checklist_answers', function (Blueprint $table): void {
            if (! Schema::hasColumn('jta_therapy_checklist_answers', 'pharmacy_id')) {
                $table->unsignedInteger('pharmacy_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('jta_therapy_checklist_answers', 'therapy_id')) {
                $table->unsignedInteger('therapy_id')->nullable()->after('pharmacy_id');
            }

            if (! Schema::hasColumn('jta_therapy_checklist_answers', 'answered_at')) {
                $table->timestamp('answered_at')->nullable()->after('answer_value');
            }
        });

        DB::statement(<<<'SQL'
UPDATE jta_therapy_checklist_answers a
SET pharmacy_id = f.pharmacy_id,
    therapy_id = f.therapy_id
FROM jta_therapy_followups f
WHERE a.followup_id = f.id
  AND (a.pharmacy_id IS NULL OR a.therapy_id IS NULL)
SQL);

        DB::statement('ALTER TABLE jta_therapy_checklist_answers ALTER COLUMN pharmacy_id SET NOT NULL');
        DB::statement('ALTER TABLE jta_therapy_checklist_answers ALTER COLUMN therapy_id SET NOT NULL');

        DB::statement('CREATE INDEX IF NOT EXISTS idx_tca_pharmacy_id ON jta_therapy_checklist_answers (pharmacy_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_tca_therapy_id ON jta_therapy_checklist_answers (therapy_id)');

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_tca_pharmacy'
          AND conrelid = 'jta_therapy_checklist_answers'::regclass
    ) THEN
        ALTER TABLE jta_therapy_checklist_answers
            ADD CONSTRAINT fk_tca_pharmacy
            FOREIGN KEY (pharmacy_id)
            REFERENCES jta_pharmas(id)
            ON DELETE CASCADE;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_tca_therapy'
          AND conrelid = 'jta_therapy_checklist_answers'::regclass
    ) THEN
        ALTER TABLE jta_therapy_checklist_answers
            ADD CONSTRAINT fk_tca_therapy
            FOREIGN KEY (therapy_id)
            REFERENCES jta_therapies(id)
            ON DELETE CASCADE;
    END IF;
END $$;
SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE jta_therapy_checklist_answers DROP CONSTRAINT IF EXISTS fk_tca_pharmacy');
        DB::statement('ALTER TABLE jta_therapy_checklist_answers DROP CONSTRAINT IF EXISTS fk_tca_therapy');

        DB::statement('DROP INDEX IF EXISTS idx_tca_pharmacy_id');
        DB::statement('DROP INDEX IF EXISTS idx_tca_therapy_id');
        DB::statement('DROP INDEX IF EXISTS idx_tf_pharmacy_id');

        Schema::table('jta_therapy_checklist_answers', function (Blueprint $table): void {
            if (Schema::hasColumn('jta_therapy_checklist_answers', 'answered_at')) {
                $table->dropColumn('answered_at');
            }

            if (Schema::hasColumn('jta_therapy_checklist_answers', 'therapy_id')) {
                $table->dropColumn('therapy_id');
            }

            if (Schema::hasColumn('jta_therapy_checklist_answers', 'pharmacy_id')) {
                $table->dropColumn('pharmacy_id');
            }
        });

        Schema::table('jta_therapy_followups', function (Blueprint $table): void {
            if (Schema::hasColumn('jta_therapy_followups', 'canceled_at')) {
                $table->dropColumn('canceled_at');
            }

            if (Schema::hasColumn('jta_therapy_followups', 'occurred_at')) {
                $table->dropColumn('occurred_at');
            }
        });
    }
};

