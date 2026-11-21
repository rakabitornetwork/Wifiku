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
        Schema::create('voucher_batches', function (Blueprint $table) {
            $table->id();
            $table->string('batch_code', 30)->unique();
            $table->foreignId('router_id')->constrained()->restrictOnDelete();
            $table->foreignId('hotspot_profile_id')->constrained()->restrictOnDelete();
            $table->foreignId('reseller_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->unsignedInteger('generated_count')->default(0);
            $table->string('prefix', 10)->nullable();
            $table->unsignedTinyInteger('code_length')->default(6);
            $table->string('charset', 20)->default('alphanumeric');
            $table->string('status', 20)->default('queued');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('voucher_batches');
    }
};
