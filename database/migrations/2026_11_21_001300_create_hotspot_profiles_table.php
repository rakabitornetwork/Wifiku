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
        Schema::create('hotspot_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('router_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('mikrotik_profile');
            $table->unsignedBigInteger('price');
            $table->unsignedBigInteger('reseller_price')->nullable();
            $table->unsignedInteger('validity_minutes');
            $table->unsignedInteger('uptime_limit_minutes')->nullable();
            $table->unsignedInteger('data_limit_mb')->nullable();
            $table->string('rate_limit')->nullable();
            $table->unsignedTinyInteger('shared_users')->default(1);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['router_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hotspot_profiles');
    }
};
