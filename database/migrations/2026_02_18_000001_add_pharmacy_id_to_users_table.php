<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'pharmacy_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->integer('pharmacy_id')->nullable()->after('email_verified_at');
                $table->index('pharmacy_id', 'users_pharmacy_id_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'pharmacy_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('users_pharmacy_id_index');
                $table->dropColumn('pharmacy_id');
            });
        }
    }
};
