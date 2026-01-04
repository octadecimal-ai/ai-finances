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
        Schema::create('wfirma_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Identyfikatory
            $table->string('wfirma_id')->index();
            $table->string('contractor_id')->nullable();
            $table->string('series_id')->nullable();
            
            // Typ i numeracja
            $table->string('type')->nullable()->index(); // normal, proforma, offer, receipt_normal, receipt_fiscal_normal, income_normal, bill, proforma_bill, offer_bill, receipt_bill, receipt_fiscal_bill, income_bill
            $table->string('number')->nullable();
            $table->string('day')->nullable();
            $table->string('month')->nullable();
            $table->string('year')->nullable();
            $table->string('fullnumber')->nullable()->index();
            $table->string('semitemplatenumber')->nullable();
            $table->string('correction_type')->nullable();
            $table->integer('corrections')->nullable();
            
            // Daty
            $table->date('date')->nullable()->index();
            $table->date('disposaldate')->nullable();
            $table->boolean('disposaldate_empty')->default(false);
            $table->string('disposaldate_format')->nullable(); // month, day
            $table->date('paymentdate')->nullable();
            $table->date('currency_date')->nullable();
            
            // Płatności
            $table->string('paymentmethod')->nullable(); // cash, transfer, compensation, cod, payment_card
            $table->string('paymentstate')->nullable()->index(); // paid, unpaid, undefined
            $table->decimal('alreadypaid_initial', 15, 2)->nullable();
            $table->decimal('alreadypaid', 15, 2)->nullable();
            
            // Waluta i kursy
            $table->string('currency', 3)->default('PLN');
            $table->decimal('currency_exchange', 15, 4)->nullable();
            $table->string('currency_label')->nullable();
            $table->decimal('price_currency_exchange', 15, 4)->nullable();
            $table->decimal('good_price_group_currency_exchange', 15, 4)->nullable();
            
            // Schematy księgowe
            $table->string('schema')->nullable(); // normal, vat_invoice_date, vat_buyer_construction_service, assessor, split_payment
            $table->boolean('schema_bill')->default(false);
            $table->boolean('schema_cancelled')->default(false);
            $table->boolean('schema_receipt_book')->default(false);
            $table->text('register_description')->nullable();
            
            // Szablony i wydruki
            $table->string('template')->nullable();
            $table->boolean('auto_send')->default(false);
            $table->text('header')->nullable();
            $table->text('footer')->nullable();
            $table->string('user_name')->nullable();
            
            // Kwoty
            $table->decimal('netto', 15, 2)->nullable();
            $table->decimal('tax', 15, 2)->nullable();
            $table->decimal('total', 15, 2)->nullable();
            $table->decimal('total_composed', 15, 2)->nullable();
            
            // Dodatkowe informacje
            $table->text('description')->nullable();
            $table->string('id_external')->nullable();
            $table->text('tags')->nullable();
            $table->string('price_type')->nullable(); // netto, brutto
            $table->string('warehouse_type')->nullable(); // simple, extended
            $table->integer('notes')->nullable();
            $table->integer('documents')->nullable();
            $table->boolean('signed')->default(false);
            $table->string('hash')->nullable();
            $table->boolean('receipt_fiscal_printed')->default(false);
            $table->string('income_lumpcode')->nullable();
            $table->string('income_correction')->nullable();
            $table->string('period')->nullable();
            
            // Synchronizacja
            $table->json('metadata')->nullable();
            $table->timestamp('synced_at')->nullable();
            
            $table->timestamps();
            
            // Indeksy
            $table->index(['user_id', 'wfirma_id']);
            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'paymentstate']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wfirma_invoices');
    }
};
