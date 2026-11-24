<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * port_block_offset: pola port forward server (port publik = (offset + oktet terakhir IP tunnel) x 100 + kode layanan).
     * adopted: akun diimpor dari router yang sudah berjalan; profile & IP-nya tidak boleh diubah oleh sinkronisasi.
     */
    public function up(): void
    {
        Schema::table('vpn_servers', function (Blueprint $table) {
            $table->unsignedSmallInteger('port_block_offset')->default(122)->after('sstp_port');
        });

        Schema::table('vpn_accounts', function (Blueprint $table) {
            $table->boolean('adopted')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('vpn_accounts', function (Blueprint $table) {
            $table->dropColumn('adopted');
        });

        Schema::table('vpn_servers', function (Blueprint $table) {
            $table->dropColumn('port_block_offset');
        });
    }
};
