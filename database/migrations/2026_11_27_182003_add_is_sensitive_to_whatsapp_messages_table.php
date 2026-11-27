<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pesan yang memuat kode atau password: isinya disamarkan setelah terkirim dan tidak pernah ditampilkan di riwayat.
     */
    public function up(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->boolean('is_sensitive')->default(false)->after('error');
        });

        // Pesan lama berjenis OTP dan tautan reset sudah terkirim: samarkan isinya sekarang.
        DB::table('whatsapp_messages')->whereIn('type', ['account_security', 'password_reset'])->update(['is_sensitive' => true, 'body' => '[Isi disembunyikan: memuat kode atau tautan rahasia]']);
    }

    public function down(): void
    {
        Schema::table('whatsapp_messages', function (Blueprint $table) {
            $table->dropColumn('is_sensitive');
        });
    }
};
