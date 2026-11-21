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
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('ont');
            $table->string('brand')->nullable();
            $table->string('model')->nullable();
            $table->string('serial_number')->unique();
            $table->string('mac_address', 17)->nullable()->unique();
            $table->string('ownership', 20)->default('company');
            $table->string('status', 20)->default('in_stock');
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('genieacs_device_id')->nullable()->unique();
            $table->decimal('last_rx_power', 6, 2)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->date('installed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'type']);
            $table->index('customer_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
