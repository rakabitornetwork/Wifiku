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
        Schema::create('outages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('odp_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('message');
            $table->string('status', 20)->default('open');
            $table->unsignedInteger('notified_count')->default(0);
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('odp_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('outages');
    }
};
