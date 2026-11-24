<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Titik belok kabel ODP → pelanggan: [[lat, lng], ...] di antara ODP dan rumah pelanggan.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->json('cable_path')->nullable()->after('longitude');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('cable_path');
        });
    }
};
