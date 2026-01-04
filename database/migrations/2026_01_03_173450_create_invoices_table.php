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
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Podstawowe informacje o fakturze
            $table->string('invoice_number')->nullable()->index();
            $table->datetime('invoice_date')->nullable();
            $table->datetime('issue_date')->nullable();
            $table->datetime('due_date')->nullable();
            
            // Sprzedawca
            $table->string('seller_name')->nullable();
            $table->string('seller_tax_id')->nullable(); // NIP
            $table->text('seller_address')->nullable();
            $table->string('seller_email')->nullable();
            $table->string('seller_phone')->nullable();
            $table->string('seller_account_number')->nullable();
            
            // Nabywca
            $table->string('buyer_name')->nullable();
            $table->string('buyer_tax_id')->nullable(); // NIP
            $table->text('buyer_address')->nullable();
            $table->string('buyer_email')->nullable();
            $table->string('buyer_phone')->nullable();
            
            // Kwoty
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('currency', 3)->default('PLN');
            
            // Płatność
            $table->string('payment_method')->nullable();
            $table->enum('payment_status', ['pending', 'paid', 'overdue', 'cancelled'])->default('pending');
            $table->datetime('paid_at')->nullable();
            
            // Plik źródłowy
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->string('source_type')->nullable(); // cursor, wfirma, etc.
            
            // Dodatkowe informacje
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable(); // Dodatkowe dane z PDF
            $table->datetime('parsed_at')->nullable();
            
            $table->timestamps();
            
            $table->index(['user_id', 'invoice_date']);
            $table->index(['invoice_number', 'user_id']);
            $table->index('payment_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
