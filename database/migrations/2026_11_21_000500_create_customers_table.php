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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('customer_code', 20)->unique();
            $table->foreignId('router_id')->constrained()->restrictOnDelete();
            $table->foreignId('package_id')->constrained()->restrictOnDelete();
            $table->foreignId('odp_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('odp_port')->nullable();
            $table->string('nik', 16)->nullable();
            $table->string('name');
            $table->string('whatsapp', 20);
            $table->text('address')->nullable();
            $table->string('village')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('pppoe_username');
            $table->text('pppoe_password');
            $table->string('billing_cycle', 20)->default('fixed');
            $table->unsignedTinyInteger('billing_day')->default(1);
            $table->unsignedTinyInteger('due_days')->default(7);
            $table->date('installed_at');
            $table->string('status', 20)->default('active');
            $table->date('promise_to_pay_until')->nullable();
            $table->timestamp('isolated_at')->nullable();
            $table->timestamp('router_synced_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['router_id', 'pppoe_username']);
            $table->index(['status', 'billing_day']);
            $table->index(['router_id', 'status']);
            $table->index('odp_id');
            $table->index('whatsapp');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
