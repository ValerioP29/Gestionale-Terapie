<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_sessions', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedInteger('pharma_id')->unique();
            $table->string('status', 20)->default('unknown'); // unknown|connecting|qr|connected|disconnected
            $table->text('last_qr')->nullable(); // QR string (se vuoi salvarlo)
            $table->timestamp('last_seen_at')->nullable();

            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('updated_at')->useCurrent();

            $table->foreign('pharma_id')->references('id')->on('jta_pharmas')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_sessions');
    }
};
