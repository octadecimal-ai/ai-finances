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
        Schema::create('finances', function (Blueprint $table) {
            $table->id();
            $table->date('data')->comment('Data transakcji');
            $table->string('opis')->comment('Opis transakcji');
            $table->decimal('kwota', 10, 2)->comment('Kwota transakcji');
            $table->string('kategoria')->nullable()->comment('Kategoria wydatku');
            $table->string('status')->nullable()->comment('Status płatności');
            $table->string('metoda_platnosci')->nullable()->comment('Metoda płatności');
            $table->string('konto')->nullable()->comment('Konto bankowe');
            $table->text('notatki')->nullable()->comment('Dodatkowe notatki');
            $table->string('source_file')->nullable()->comment('Plik źródłowy');
            $table->string('source_id')->nullable()->comment('ID z pliku źródłowego');
            $table->timestamps();
            
            // Indeksy dla lepszej wydajności
            $table->index('data');
            $table->index('kategoria');
            $table->index('status');
            $table->index(['data', 'kategoria']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('finances');
    }
};
