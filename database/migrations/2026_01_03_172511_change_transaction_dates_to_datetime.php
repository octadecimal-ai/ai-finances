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
            $table->datetime('transaction_date')->change();
            $table->datetime('value_date')->nullable()->change();
            $table->datetime('booking_date')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->date('transaction_date')->change();
            $table->date('value_date')->nullable()->change();
            $table->date('booking_date')->nullable()->change();
        });
    }
};
