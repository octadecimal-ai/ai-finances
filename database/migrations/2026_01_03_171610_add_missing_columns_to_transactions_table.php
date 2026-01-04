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
        Schema::table('transactions', function (Blueprint $table) {
            $table->datetime('booking_date')->nullable()->after('transaction_date');
            $table->decimal('balance_after', 15, 2)->nullable()->after('status');
            $table->json('metadata')->nullable()->after('notes');
            $table->string('provider')->nullable()->after('external_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn(['booking_date', 'balance_after', 'metadata', 'provider']);
        });
    }
};
