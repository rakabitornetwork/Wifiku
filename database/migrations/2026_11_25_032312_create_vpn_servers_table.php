<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Server VPN Tunnel: router MikroTik milik penyedia yang menerima koneksi L2TP/IPsec dan SSTP pelanggan.
     */
    public function up(): void
    {
        Schema::create('vpn_servers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('public_host');
            $table->string('local_address', 45);
            $table->string('pool_start', 45);
            $table->string('pool_end', 45);
            $table->text('ipsec_secret')->nullable();
            $table->unsignedSmallInteger('sstp_port')->default(443);
            $table->boolean('l2tp_enabled')->default(true);
            $table->boolean('sstp_enabled')->default(true);
            $table->boolean('is_active')->default(true);
            $table->timestamp('setup_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vpn_servers');
    }
};
