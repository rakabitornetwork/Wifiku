<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Akun VPN nonaktif boleh dihapus tanpa menghilangkan riwayat tagihannya: relasi invoice dilepas (NULL), bukan ditolak.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['vpn_account_id']);
            $table->foreign('vpn_account_id')->references('id')->on('vpn_accounts')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['vpn_account_id']);
            $table->foreign('vpn_account_id')->references('id')->on('vpn_accounts')->restrictOnDelete();
        });
    }
};
