<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kapan router mulai tidak terjangkau, untuk widget "Router offline" di dashboard.
     * Router yang sudah offline sebelum kolom ini ada diisi pada pengecekan berikutnya.
     */
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->timestamp('offline_since')->nullable()->after('last_checked_at');
        });
    }

    public function down(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            $table->dropColumn('offline_since');
        });
    }
};
