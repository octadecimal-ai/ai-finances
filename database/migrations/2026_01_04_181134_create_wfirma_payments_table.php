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
        Schema::create('wfirma_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Identyfikatory
            $table->string('wfirma_id')->index();
            $table->string('invoice_id')->nullable()->index();
            $table->string('expense_id')->nullable()->index();
            $table->string('income_id')->nullable()->index();
            $table->string('contractor_id')->nullable();
            $table->string('bank_account_id')->nullable();
            $table->string('payment_cashbox_id')->nullable();
            
            // Informacje o płatności
            $table->date('date')->nullable()->index();
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency', 3)->default('PLN');
            $table->string('payment_method')->nullable(); // cash, transfer, compensation, cod, payment_card
            $table->string('status')->nullable();
            $table->text('description')->nullable();
            
            // Synchronizacja
            $table->json('metadata')->nullable();
            $table->timestamp('synced_at')->nullable();
            
            $table->timestamps();
            
            // Indeksy
            $table->index(['user_id', 'wfirma_id']);
            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'invoice_id']);
            $table->index(['user_id', 'expense_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wfirma_payments');
    }
};
