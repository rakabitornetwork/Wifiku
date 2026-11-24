<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Akun VPN Tunnel yang dijual: satu akun = satu PPP secret di server + satu tunnel di router pelanggan.
     */
    public function up(): void
    {
        Schema::create('vpn_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vpn_server_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('whatsapp', 20)->nullable();
            $table->string('protocol', 10);
            $table->string('username');
            $table->text('password');
            $table->string('remote_address', 45);
            $table->json('forwards')->nullable();
            $table->string('status', 10)->default('active');
            $table->date('expires_at')->nullable();
            $table->unsignedInteger('monthly_price')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['vpn_server_id', 'username']);
            $table->unique(['vpn_server_id', 'remote_address']);
            $table->index(['status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vpn_accounts');
    }
};
