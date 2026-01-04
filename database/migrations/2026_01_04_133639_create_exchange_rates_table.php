<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exchange_rates', function (Blueprint $table) {
            $table->id();
            $table->date('date'); // Data kursu (YYYY-MM-DD)
            $table->string('currency_code', 10); // Kod waluty (USD, EUR, GBP, etc.)
            $table->decimal('rate', 15, 6); // Kurs wymiany
            $table->string('table_number', 50)->nullable(); // Nr tabeli (np. 1, 2, 3)
            $table->string('full_table_number', 100)->nullable(); // Pełny numer tabeli (np. 001/A/NBP/2025)
            $table->timestamps();

            // Indeksy dla szybkiego wyszukiwania
            $table->index(['date', 'currency_code']);
            $table->index('currency_code');
            $table->index('date');
            
            // Unikalność: jeden kurs dla danej daty i waluty
            $table->unique(['date', 'currency_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exchange_rates');
    }
};
