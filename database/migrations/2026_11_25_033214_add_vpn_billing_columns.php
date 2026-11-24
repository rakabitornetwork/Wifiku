<?php

use App\Models\VpnAccount;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Tagihan VPN memakai tabel invoice/payment yang sama: customer_id menjadi opsional dan
     * invoice dapat menunjuk ke akun VPN. Akun VPN mendapat token halaman bayar publik.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->change();
            $table->foreignId('vpn_account_id')->nullable()->after('customer_id')->constrained()->restrictOnDelete();
            $table->unique(['vpn_account_id', 'period_start']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('customer_id')->nullable()->change();
        });

        Schema::table('vpn_accounts', function (Blueprint $table) {
            $table->string('portal_token', 40)->nullable()->unique()->after('username');
            $table->timestamp('auto_disabled_at')->nullable()->after('status');
        });

        VpnAccount::query()->whereNull('portal_token')->each(
            fn (VpnAccount $account) => $account->forceFill(['portal_token' => Str::random(40)])->saveQuietly(),
        );
    }

    public function down(): void
    {
        Schema::table('vpn_accounts', function (Blueprint $table) {
            $table->dropColumn(['portal_token', 'auto_disabled_at']);
        });

        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['vpn_account_id', 'period_start']);
            $table->dropConstrainedForeignId('vpn_account_id');
        });
    }
};
