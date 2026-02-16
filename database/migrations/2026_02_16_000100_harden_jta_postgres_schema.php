<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('jta_assistants')) {
            if (! Schema::hasColumn('jta_assistants', 'pharmacy_id')) {
                Schema::table('jta_assistants', function (Blueprint $table) {
                    // Backward-compatible alias for deprecated pharma_id.
                    $table->unsignedInteger('pharmacy_id')->nullable()->after('pharma_id');
                });
            }

            DB::statement('UPDATE jta_assistants SET pharmacy_id = pharma_id WHERE pharmacy_id IS NULL AND pharma_id IS NOT NULL');
            DB::statement("COMMENT ON COLUMN jta_assistants.pharma_id IS 'deprecated: use pharmacy_id'");

            DB::statement('CREATE INDEX IF NOT EXISTS idx_assistants_pharmacy_id ON jta_assistants (pharmacy_id)');
            DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_assistants_pharmacy_id'
          AND conrelid = 'jta_assistants'::regclass
    ) THEN
        ALTER TABLE jta_assistants
            ADD CONSTRAINT fk_assistants_pharmacy_id
            FOREIGN KEY (pharmacy_id)
            REFERENCES jta_pharmas(id)
            ON DELETE CASCADE;
    END IF;
END $$;
SQL);
        }

        if (Schema::hasTable('jta_therapy_reminders')) {
            if (! Schema::hasColumn('jta_therapy_reminders', 'pharmacy_id')) {
                Schema::table('jta_therapy_reminders', function (Blueprint $table) {
                    // Nullable for safe rollout on existing rows; backfilled below.
                    $table->unsignedInteger('pharmacy_id')->nullable()->after('therapy_id');
                });
            }

            DB::statement(<<<'SQL'
UPDATE jta_therapy_reminders r
SET pharmacy_id = t.pharmacy_id
FROM jta_therapies t
WHERE r.therapy_id = t.id
  AND r.pharmacy_id IS NULL
SQL);

            DB::statement('CREATE INDEX IF NOT EXISTS idx_tr_pharmacy_due ON jta_therapy_reminders (pharmacy_id, next_due_at)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_tr_therapy_status ON jta_therapy_reminders (therapy_id, status)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_tr_next_due_not_null ON jta_therapy_reminders (next_due_at) WHERE next_due_at IS NOT NULL');

            DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_tr_pharmacy'
          AND conrelid = 'jta_therapy_reminders'::regclass
    ) THEN
        ALTER TABLE jta_therapy_reminders
            ADD CONSTRAINT fk_tr_pharmacy
            FOREIGN KEY (pharmacy_id)
            REFERENCES jta_pharmas(id)
            ON DELETE CASCADE;
    END IF;
END $$;
SQL);

            DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_tr_therapy'
          AND conrelid = 'jta_therapy_reminders'::regclass
    ) THEN
        ALTER TABLE jta_therapy_reminders
            ADD CONSTRAINT fk_tr_therapy
            FOREIGN KEY (therapy_id)
            REFERENCES jta_therapies(id)
            ON DELETE CASCADE;
    END IF;
END $$;
SQL);
        }

        if (Schema::hasTable('jta_therapy_followups')) {
            if (! Schema::hasColumn('jta_therapy_followups', 'pharmacy_id')) {
                Schema::table('jta_therapy_followups', function (Blueprint $table) {
                    // Nullable for safe rollout on existing rows; backfilled below.
                    $table->unsignedInteger('pharmacy_id')->nullable()->after('therapy_id');
                });
            }

            DB::statement(<<<'SQL'
UPDATE jta_therapy_followups f
SET pharmacy_id = t.pharmacy_id
FROM jta_therapies t
WHERE f.therapy_id = t.id
  AND f.pharmacy_id IS NULL
SQL);

            DB::statement('CREATE INDEX IF NOT EXISTS idx_tf_therapy_created ON jta_therapy_followups (therapy_id, created_at)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_tf_therapy_entry_type ON jta_therapy_followups (therapy_id, entry_type)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_tf_pharmacy_created ON jta_therapy_followups (pharmacy_id, created_at)');

            DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_tf_therapy'
          AND conrelid = 'jta_therapy_followups'::regclass
    ) THEN
        ALTER TABLE jta_therapy_followups
            ADD CONSTRAINT fk_tf_therapy
            FOREIGN KEY (therapy_id)
            REFERENCES jta_therapies(id)
            ON DELETE CASCADE;
    END IF;
END $$;
SQL);

            DB::statement(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_tf_pharmacy'
          AND conrelid = 'jta_therapy_followups'::regclass
          AND confdeltype = 'n'
    ) THEN
        ALTER TABLE jta_therapy_followups
            DROP CONSTRAINT fk_tf_pharmacy;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_tf_pharmacy'
          AND conrelid = 'jta_therapy_followups'::regclass
    ) THEN
        ALTER TABLE jta_therapy_followups
            ADD CONSTRAINT fk_tf_pharmacy
            FOREIGN KEY (pharmacy_id)
            REFERENCES jta_pharmas(id)
            ON DELETE CASCADE;
    END IF;
END $$;
SQL);
        }

        if (Schema::hasTable('jta_therapies')) {
            if (! Schema::hasColumn('jta_therapies', 'deleted_at')) {
                Schema::table('jta_therapies', function (Blueprint $table) {
                    $table->timestamp('deleted_at')->nullable()->after('updated_at');
                });
            }

            DB::statement('CREATE INDEX IF NOT EXISTS idx_therapy_pharmacy_deleted_at ON jta_therapies (pharmacy_id, deleted_at)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_therapy_pharmacy_status ON jta_therapies (pharmacy_id, status)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_therapy_pharmacy_patient ON jta_therapies (pharmacy_id, patient_id)');

            DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_therapy_pharma'
          AND conrelid = 'jta_therapies'::regclass
    ) THEN
        ALTER TABLE jta_therapies
            ADD CONSTRAINT fk_therapy_pharma
            FOREIGN KEY (pharmacy_id)
            REFERENCES jta_pharmas(id)
            ON DELETE CASCADE;
    END IF;
END $$;
SQL);

            DB::statement(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_therapy_patient'
          AND conrelid = 'jta_therapies'::regclass
          AND confdeltype = 'c'
    ) THEN
        ALTER TABLE jta_therapies
            DROP CONSTRAINT fk_therapy_patient;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_therapy_patient'
          AND conrelid = 'jta_therapies'::regclass
    ) THEN
        ALTER TABLE jta_therapies
            ADD CONSTRAINT fk_therapy_patient
            FOREIGN KEY (patient_id)
            REFERENCES jta_patients(id)
            ON DELETE RESTRICT;
    END IF;
END $$;
SQL);
        }

        if (Schema::hasTable('jta_therapy_reports')) {
            DB::statement('CREATE INDEX IF NOT EXISTS idx_trp_pharmacy_therapy_created ON jta_therapy_reports (pharmacy_id, therapy_id, created_at)');
            DB::statement('CREATE INDEX IF NOT EXISTS idx_trp_share_token ON jta_therapy_reports (share_token)');
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS uqidx_trp_pharmacy_share_token_not_null ON jta_therapy_reports (pharmacy_id, share_token) WHERE share_token IS NOT NULL');

            DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_r_therapy'
          AND conrelid = 'jta_therapy_reports'::regclass
    ) THEN
        ALTER TABLE jta_therapy_reports
            ADD CONSTRAINT fk_r_therapy
            FOREIGN KEY (therapy_id)
            REFERENCES jta_therapies(id)
            ON DELETE CASCADE;
    END IF;
END $$;
SQL);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('jta_therapy_reports')) {
            DB::statement('DROP INDEX IF EXISTS uqidx_trp_pharmacy_share_token_not_null');
            DB::statement('DROP INDEX IF EXISTS idx_trp_pharmacy_therapy_created');
            DB::statement('DROP INDEX IF EXISTS idx_trp_share_token');
        }

        if (Schema::hasTable('jta_therapies')) {
            DB::statement('DROP INDEX IF EXISTS idx_therapy_pharmacy_deleted_at');
            DB::statement('DROP INDEX IF EXISTS idx_therapy_pharmacy_status');
            DB::statement('DROP INDEX IF EXISTS idx_therapy_pharmacy_patient');

            DB::statement(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_therapy_patient'
          AND conrelid = 'jta_therapies'::regclass
          AND confdeltype = 'r'
    ) THEN
        ALTER TABLE jta_therapies
            DROP CONSTRAINT fk_therapy_patient;

        ALTER TABLE jta_therapies
            ADD CONSTRAINT fk_therapy_patient
            FOREIGN KEY (patient_id)
            REFERENCES jta_patients(id)
            ON DELETE CASCADE;
    END IF;
END $$;
SQL);

            if (Schema::hasColumn('jta_therapies', 'deleted_at')) {
                Schema::table('jta_therapies', function (Blueprint $table) {
                    $table->dropColumn('deleted_at');
                });
            }
        }

        if (Schema::hasTable('jta_therapy_followups')) {
            DB::statement('DROP INDEX IF EXISTS idx_tf_therapy_created');
            DB::statement('DROP INDEX IF EXISTS idx_tf_therapy_entry_type');
            DB::statement('DROP INDEX IF EXISTS idx_tf_pharmacy_created');

            DB::statement(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1 FROM pg_constraint
        WHERE conname = 'fk_tf_pharmacy'
          AND conrelid = 'jta_therapy_followups'::regclass
          AND confdeltype = 'c'
    ) THEN
        ALTER TABLE jta_therapy_followups
            DROP CONSTRAINT fk_tf_pharmacy;

        ALTER TABLE jta_therapy_followups
            ADD CONSTRAINT fk_tf_pharmacy
            FOREIGN KEY (pharmacy_id)
            REFERENCES jta_pharmas(id)
            ON DELETE SET NULL;
    END IF;
END $$;
SQL);

            if (Schema::hasColumn('jta_therapy_followups', 'pharmacy_id')) {
                // Drop only if introduced by this migration on legacy schemas.
                DB::statement(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_name = 'jta_therapy_followups'
          AND column_name = 'pharmacy_id'
    ) THEN
        -- Column exists in baseline schema, keep it for compatibility.
        NULL;
    END IF;
END $$;
SQL);
            }
        }

        if (Schema::hasTable('jta_therapy_reminders')) {
            DB::statement('ALTER TABLE jta_therapy_reminders DROP CONSTRAINT IF EXISTS fk_tr_pharmacy');
            DB::statement('DROP INDEX IF EXISTS idx_tr_pharmacy_due');
            DB::statement('DROP INDEX IF EXISTS idx_tr_therapy_status');
            DB::statement('DROP INDEX IF EXISTS idx_tr_next_due_not_null');

            if (Schema::hasColumn('jta_therapy_reminders', 'pharmacy_id')) {
                Schema::table('jta_therapy_reminders', function (Blueprint $table) {
                    $table->dropColumn('pharmacy_id');
                });
            }
        }

        if (Schema::hasTable('jta_assistants')) {
            DB::statement('ALTER TABLE jta_assistants DROP CONSTRAINT IF EXISTS fk_assistants_pharmacy_id');
            DB::statement('DROP INDEX IF EXISTS idx_assistants_pharmacy_id');

            if (Schema::hasColumn('jta_assistants', 'pharmacy_id')) {
                Schema::table('jta_assistants', function (Blueprint $table) {
                    $table->dropColumn('pharmacy_id');
                });
            }
        }
    }
};
