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
        Schema::create('wfirma_invoice_contents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('wfirma_invoices')->onDelete('cascade');
            
            // Identyfikatory
            $table->string('wfirma_id')->index();
            
            // Informacje o pozycji
            $table->string('name')->nullable();
            $table->string('classification')->nullable(); // PKWiU
            $table->string('unit')->nullable();
            $table->string('unit_id')->nullable();
            $table->decimal('count', 15, 4)->nullable();
            $table->decimal('unit_count', 15, 4)->nullable();
            
            // Ceny i rabaty
            $table->decimal('price', 15, 2)->nullable();
            $table->boolean('price_modified')->default(false);
            $table->decimal('discount', 15, 2)->nullable();
            $table->decimal('discount_percent', 15, 2)->nullable();
            
            // VAT
            $table->decimal('vat', 15, 2)->nullable();
            $table->string('vat_code_id')->nullable();
            
            // Kwoty
            $table->decimal('netto', 15, 2)->nullable();
            $table->decimal('brutto', 15, 2)->nullable();
            $table->string('lumpcode')->nullable();
            
            // Powiązania
            $table->string('good_id')->nullable();
            $table->string('tangiblefixedasset_id')->nullable();
            $table->string('equipment_id')->nullable();
            $table->string('vehicle_id')->nullable();
            
            // Dodatkowe
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Indeksy
            $table->index(['invoice_id', 'wfirma_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wfirma_invoice_contents');
    }
};
