<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Penanda pengingat hari jatuh tempo, terpisah dari reminder_sent_at (pengingat H-3) agar tiap invoice
     * mendapat keduanya tepat satu kali.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->timestamp('due_reminder_sent_at')->nullable()->after('reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('due_reminder_sent_at');
        });
    }
};
