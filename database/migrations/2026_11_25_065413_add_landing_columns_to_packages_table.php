<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Paket yang tampil di bagian Harga pada landing page publik.
     */
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->boolean('show_on_landing')->default(false)->after('is_active');
            $table->boolean('landing_highlight')->default(false)->after('show_on_landing');
            $table->string('landing_description', 160)->nullable()->after('landing_highlight');
        });
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['show_on_landing', 'landing_highlight', 'landing_description']);
        });
    }
};
