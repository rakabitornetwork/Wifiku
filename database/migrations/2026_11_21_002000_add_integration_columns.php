<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('routers', function (Blueprint $table) {
            // Rahasia bersama untuk webhook on-login hotspot dari router ini (terenkripsi).
            $table->text('webhook_token')->nullable()->after('isolir_profile');
        });

        Schema::table('invoices', function (Blueprint $table) {
            // Satu invoice per siklus tagihan, termasuk invoice prorata yang period_start-nya berbeda.
            $table->unique(['customer_id', 'period_end']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropUnique(['customer_id', 'period_end']);
        });

        Schema::table('routers', function (Blueprint $table) {
            $table->dropColumn('webhook_token');
        });
    }
};
