<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Riwayat pesan Telegram ke pelanggan (otomatis dan balasan bot), setara whatsapp_messages untuk WhatsApp.
     */
    public function up(): void
    {
        Schema::create('telegram_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('chat_id', 40);
            $table->string('type', 20)->nullable();
            $table->text('body');
            $table->string('status', 12)->default('queued');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error')->nullable();
            $table->boolean('is_sensitive')->default(false);
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
            $table->index(['customer_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telegram_messages');
    }
};
