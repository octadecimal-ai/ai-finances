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
        Schema::create('invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->onDelete('cascade');
            
            // Pozycja na fakturze
            $table->integer('position')->default(0);
            
            // Opis pozycji
            $table->string('name');
            $table->text('description')->nullable();
            
            // Ilość i ceny
            $table->decimal('quantity', 10, 3)->default(1);
            $table->string('unit')->nullable(); // szt, kg, m, etc.
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('net_amount', 15, 2)->default(0);
            
            // VAT
            $table->decimal('tax_rate', 5, 2)->default(0); // 23, 8, 5, 0
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('gross_amount', 15, 2)->default(0);
            
            // Kategoria (opcjonalnie)
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
            
            $table->timestamps();
            
            $table->index(['invoice_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_items');
    }
};
