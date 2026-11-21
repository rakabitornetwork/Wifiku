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
        Schema::create('routers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('host');
            $table->unsignedSmallInteger('api_port')->default(8728);
            $table->boolean('use_ssl')->default(false);
            $table->string('username');
            $table->text('password');
            $table->string('isolir_profile')->default('ISOLIR');
            $table->string('status', 20)->default('unknown');
            $table->timestamp('last_checked_at')->nullable();
            $table->string('last_error')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['host', 'api_port']);
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('routers');
    }
};
