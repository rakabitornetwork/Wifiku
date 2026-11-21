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
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_batch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('router_id')->constrained()->restrictOnDelete();
            $table->foreignId('hotspot_profile_id')->constrained()->restrictOnDelete();
            $table->string('code', 32);
            $table->string('password', 32);
            $table->unsignedBigInteger('price');
            $table->string('status', 20)->default('unused');
            $table->boolean('synced_to_router')->default(false);
            $table->timestamp('first_login_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('mac_address', 17)->nullable();
            $table->timestamps();

            $table->unique(['router_id', 'code']);
            $table->index(['voucher_batch_id', 'status']);
            $table->index(['status', 'expires_at']);
            $table->index(['router_id', 'first_login_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vouchers');
    }
};
