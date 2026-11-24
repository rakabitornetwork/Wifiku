<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Token reset password pelanggan VPN, terpisah dari pengguna admin agar email yang sama tidak saling menimpa.
     */
    public function up(): void
    {
        Schema::create('vpn_member_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vpn_member_password_reset_tokens');
    }
};
