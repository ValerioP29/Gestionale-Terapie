<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('jta_therapy_assistant')) {
            Schema::create('jta_therapy_assistant', function (Blueprint $table): void {
                $table->increments('id');
                $table->unsignedInteger('pharmacy_id');
                $table->unsignedInteger('therapy_id');
                $table->unsignedInteger('assistant_id');
                $table->string('role', 50)->nullable();
                $table->string('contact_channel', 30)->nullable();
                $table->jsonb('preferences_json')->nullable();
                $table->jsonb('consents_json')->nullable();
                $table->timestamps();

                $table->index(['pharmacy_id', 'therapy_id'], 'idx_ta_pharmacy_therapy');
                $table->index(['pharmacy_id', 'assistant_id'], 'idx_ta_pharmacy_assistant');
                $table->unique(['pharmacy_id', 'therapy_id', 'assistant_id'], 'uq_ta_pharmacy_therapy_assistant');

                $table->foreign('pharmacy_id', 'fk_ta_pharmacy')
                    ->references('id')
                    ->on('jta_pharmas')
                    ->cascadeOnDelete();

                $table->foreign('therapy_id', 'fk_ta_therapy')
                    ->references('id')
                    ->on('jta_therapies')
                    ->cascadeOnDelete();

                $table->foreign('assistant_id', 'fk_ta_assistant')
                    ->references('id')
                    ->on('jta_assistants')
                    ->cascadeOnDelete();
            });

            return;
        }

        Schema::table('jta_therapy_assistant', function (Blueprint $table): void {
            if (! Schema::hasColumn('jta_therapy_assistant', 'pharmacy_id')) {
                $table->unsignedInteger('pharmacy_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('jta_therapy_assistant', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });

        DB::statement(<<<'SQL'
UPDATE jta_therapy_assistant ta
SET pharmacy_id = t.pharmacy_id
FROM jta_therapies t
WHERE ta.therapy_id = t.id
  AND ta.pharmacy_id IS NULL
SQL);

        DB::statement('ALTER TABLE jta_therapy_assistant ALTER COLUMN pharmacy_id SET NOT NULL');

        DB::statement('ALTER TABLE jta_therapy_assistant ALTER COLUMN role TYPE varchar(50) USING role::text');
        DB::statement('ALTER TABLE jta_therapy_assistant ALTER COLUMN role DROP NOT NULL');
        DB::statement('ALTER TABLE jta_therapy_assistant ALTER COLUMN contact_channel TYPE varchar(30)');

        DB::statement('CREATE INDEX IF NOT EXISTS idx_ta_pharmacy_therapy ON jta_therapy_assistant (pharmacy_id, therapy_id)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_ta_pharmacy_assistant ON jta_therapy_assistant (pharmacy_id, assistant_id)');

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'uq_ta_unique'
          AND conrelid = 'jta_therapy_assistant'::regclass
    ) THEN
        ALTER TABLE jta_therapy_assistant DROP CONSTRAINT uq_ta_unique;
    END IF;
END $$;
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'uq_ta_pharmacy_therapy_assistant'
          AND conrelid = 'jta_therapy_assistant'::regclass
    ) THEN
        ALTER TABLE jta_therapy_assistant
            ADD CONSTRAINT uq_ta_pharmacy_therapy_assistant
            UNIQUE (pharmacy_id, therapy_id, assistant_id);
    END IF;
END $$;
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'fk_ta_pharmacy'
          AND conrelid = 'jta_therapy_assistant'::regclass
    ) THEN
        ALTER TABLE jta_therapy_assistant
            ADD CONSTRAINT fk_ta_pharmacy
            FOREIGN KEY (pharmacy_id)
            REFERENCES jta_pharmas(id)
            ON DELETE CASCADE;
    END IF;
END $$;
SQL);
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS idx_ta_pharmacy_therapy');
        DB::statement('DROP INDEX IF EXISTS idx_ta_pharmacy_assistant');

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'uq_ta_pharmacy_therapy_assistant'
          AND conrelid = 'jta_therapy_assistant'::regclass
    ) THEN
        ALTER TABLE jta_therapy_assistant DROP CONSTRAINT uq_ta_pharmacy_therapy_assistant;
    END IF;
END $$;
SQL);

        DB::statement(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM pg_constraint
        WHERE conname = 'fk_ta_pharmacy'
          AND conrelid = 'jta_therapy_assistant'::regclass
    ) THEN
        ALTER TABLE jta_therapy_assistant DROP CONSTRAINT fk_ta_pharmacy;
    END IF;
END $$;
SQL);
    }
};
