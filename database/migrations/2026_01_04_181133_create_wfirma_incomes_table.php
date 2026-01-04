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
        Schema::create('wfirma_incomes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Identyfikatory
            $table->string('wfirma_id')->index();
            $table->string('contractor_id')->nullable();
            
            // Typ i numeracja
            $table->string('type')->nullable()->index();
            $table->string('fullnumber')->nullable()->index();
            $table->string('number')->nullable();
            
            // Daty
            $table->date('date')->nullable()->index();
            $table->date('taxregister_date')->nullable();
            $table->date('payment_date')->nullable();
            
            // Płatności
            $table->string('payment_method')->nullable(); // cash, transfer, compensation, cod, payment_card
            $table->boolean('paid')->default(false)->index();
            $table->decimal('alreadypaid_initial', 15, 2)->nullable();
            
            // Waluta i księgowość
            $table->string('currency', 3)->default('PLN');
            $table->string('accounting_effect')->nullable(); // kpir_and_vat, kpir, vat, nothing
            $table->string('warehouse_type')->nullable(); // simple, extended
            $table->string('tax_evaluation_method')->nullable(); // netto, brutto
            
            // Opcje VAT
            $table->boolean('schema_vat_cashbox')->default(false);
            $table->boolean('wnt')->default(false);
            $table->boolean('service_import')->default(false);
            $table->boolean('service_import2')->default(false);
            $table->boolean('cargo_import')->default(false);
            $table->boolean('split_payment')->default(false);
            
            // Status
            $table->boolean('draft')->default(false)->index();
            
            // Kwoty
            $table->decimal('netto', 15, 2)->nullable();
            $table->decimal('brutto', 15, 2)->nullable();
            $table->decimal('vat_content_netto', 15, 2)->nullable();
            $table->decimal('vat_content_tax', 15, 2)->nullable();
            $table->decimal('vat_content_brutto', 15, 2)->nullable();
            $table->decimal('total', 15, 2)->nullable();
            $table->decimal('remaining', 15, 2)->nullable();
            
            // Dodatkowe
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('synced_at')->nullable();
            
            $table->timestamps();
            
            // Indeksy
            $table->index(['user_id', 'wfirma_id']);
            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'paid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wfirma_incomes');
    }
};
