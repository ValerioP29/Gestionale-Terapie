<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('jta_therapy_chronic_care', function (Blueprint $table): void {
            if (! Schema::hasColumn('jta_therapy_chronic_care', 'custom_condition_name')) {
                $table->string('custom_condition_name', 120)->nullable()->after('primary_condition');
            }
        });
    }

    public function down(): void
    {
        Schema::table('jta_therapy_chronic_care', function (Blueprint $table): void {
            if (Schema::hasColumn('jta_therapy_chronic_care', 'custom_condition_name')) {
                $table->dropColumn('custom_condition_name');
            }
        });
    }
};
