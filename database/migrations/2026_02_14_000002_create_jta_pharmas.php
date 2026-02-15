<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jta_pharmas', function (Blueprint $table) {
            $table->increments('id');

            $table->string('email', 100);
            $table->string('slug_name', 100);     // “uguale alla email” nel tuo schema
            $table->string('slug_url', 100)->nullable();

            $table->string('phone_number', 15)->nullable();
            $table->string('password', 255);

            // in MySQL: tinyint(1) con valori 0,1,-1 -> in Postgres meglio smallint
            $table->smallInteger('status_id')->default(0);

            $table->string('business_name', 100);
            $table->string('nice_name', 100);

            $table->string('city', 100)->nullable();
            $table->text('address')->nullable();
            $table->string('latlng', 30)->nullable();

            $table->text('description')->nullable();
            $table->string('logo', 255)->nullable();

            // nel dump è longtext “bin”, ma contiene JSON stringificato -> TEXT per compatibilità import
            $table->longText('working_info')->nullable();

            $table->text('prompt')->nullable();

            $table->string('img_avatar', 100)->nullable();
            $table->string('img_cover', 100)->nullable();
            $table->string('img_bot', 100)->nullable();

            $table->boolean('is_deleted')->default(false);

            $table->enum('status', ['active','inactive','deleted'])->default('active');

            // nel tuo schema sono nullable
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            $table->timestamp('last_access')->nullable();

            // indici come da dump
            $table->index('is_deleted', 'is_deleted');
            $table->index('slug_url', 'slug_url');
            $table->index('logo', 'idx_logo');
        });

        // trigger updated_at (equivalente del ON UPDATE CURRENT_TIMESTAMP di MySQL)
        DB::statement("CREATE TRIGGER set_updated_at_jta_pharmas
            BEFORE UPDATE ON jta_pharmas
            FOR EACH ROW EXECUTE FUNCTION set_updated_at();");
    }

    public function down(): void
    {
        Schema::dropIfExists('jta_pharmas');
    }
};
