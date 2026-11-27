<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Keadaan percakapan bot pelanggan di WhatsApp dan Telegram: nomor terverifikasi, sambungan terpilih,
     * dan langkah yang sedang berjalan (mis. menunggu konfirmasi ganti WiFi). Isi payload terenkripsi.
     */
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('channel', 12);
            $table->string('sender_id', 40);
            $table->string('phone', 20)->nullable();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('state', 24)->default('idle');
            $table->text('payload')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['channel', 'sender_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};
