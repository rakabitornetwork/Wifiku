<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IPsec untuk L2TP bersifat opsional: server tanpa IPsec secret memakai L2TP polos.
     */
    public function up(): void
    {
        Schema::table('vpn_servers', function (Blueprint $table) {
            $table->boolean('use_ipsec')->default(true)->after('ipsec_secret');
        });
    }

    public function down(): void
    {
        Schema::table('vpn_servers', function (Blueprint $table) {
            $table->dropColumn('use_ipsec');
        });
    }
};
