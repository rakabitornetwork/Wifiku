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
        Schema::create('payment_callbacks', function (Blueprint $table) {
            $table->id();
            $table->string('driver', 20);
            $table->string('merchant_ref', 64)->nullable();
            $table->boolean('signature_valid')->default(false);
            $table->json('payload');
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index('merchant_ref');
            $table->index(['driver', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_callbacks');
    }
};
