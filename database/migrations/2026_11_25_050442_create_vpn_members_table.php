<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pelanggan layanan VPN Tunnel yang mendaftar sendiri lewat landing page (login terpisah dari admin).
     */
    public function up(): void
    {
        Schema::create('vpn_members', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('whatsapp', 20);
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::table('vpn_accounts', function (Blueprint $table) {
            $table->foreignId('vpn_member_id')->nullable()->after('vpn_server_id')->constrained()->restrictOnDelete();
            $table->index(['vpn_member_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('vpn_accounts', function (Blueprint $table) {
            $table->dropIndex(['vpn_member_id', 'status']);
            $table->dropConstrainedForeignId('vpn_member_id');
        });

        Schema::dropIfExists('vpn_members');
    }
};
