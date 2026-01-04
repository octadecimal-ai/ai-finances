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
        Schema::create('wfirma_expense_parts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('expense_id')->constrained('wfirma_expenses')->onDelete('cascade');
            
            // Identyfikatory
            $table->string('wfirma_id')->index();
            
            // Typ i schemat
            $table->string('expense_part_type')->nullable(); // rates, positions
            $table->string('schema')->nullable(); // cost, purchase_trade_goods, vehicle_fuel, vehicle_expense
            $table->string('good_action')->nullable(); // new
            $table->string('good_id')->nullable();
            
            // Informacje o pozycji
            $table->string('name')->nullable();
            $table->string('classification')->nullable(); // PKWiU
            $table->string('unit')->nullable();
            $table->string('unit_id')->nullable();
            $table->decimal('count', 15, 4)->nullable();
            
            // Ceny i VAT
            $table->decimal('price', 15, 2)->nullable();
            $table->string('vat_code_id')->nullable();
            
            // Kwoty
            $table->decimal('netto', 15, 2)->nullable();
            $table->decimal('brutto', 15, 2)->nullable();
            $table->decimal('vat', 15, 2)->nullable();
            
            // Rabaty
            $table->decimal('discount', 15, 2)->nullable();
            $table->decimal('discount_percent', 15, 2)->nullable();
            
            // Dodatkowe
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Indeksy
            $table->index(['expense_id', 'wfirma_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wfirma_expense_parts');
    }
};
