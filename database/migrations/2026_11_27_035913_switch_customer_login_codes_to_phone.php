<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Login portal pelanggan memakai nomor WhatsApp: kode OTP kini milik nomor, bukan satu pelanggan
     * (satu nomor bisa dipakai beberapa sambungan). Isi tabel hanya kode sementara, jadi dibuat ulang.
     */
    public function up(): void
    {
        Schema::dropIfExists('customer_login_codes');

        Schema::create('customer_login_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->unique();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_login_codes');

        Schema::create('customer_login_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at');
            $table->timestamp('expires_at');
            $table->timestamps();
        });
    }
};
