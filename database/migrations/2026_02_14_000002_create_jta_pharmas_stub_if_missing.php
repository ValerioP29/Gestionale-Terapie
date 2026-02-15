<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('jta_pharmas')) {
            Schema::create('jta_pharmas', function (Blueprint $table) {
                $table->increments('id');
                $table->string('name', 255)->nullable();
                $table->timestamp('created_at')->useCurrent();
                $table->timestamp('updated_at')->useCurrent();
            });

            DB::statement("CREATE TRIGGER set_updated_at_jta_pharmas
                BEFORE UPDATE ON jta_pharmas
                FOR EACH ROW EXECUTE FUNCTION set_updated_at();");
        }
    }

    public function down(): void
    {
        // se era stub e vuoi rollback, lo togliamo
        if (Schema::hasTable('jta_pharmas')) {
            Schema::dropIfExists('jta_pharmas');
        }
    }
};
