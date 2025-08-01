<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('bank_account_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('category_id')->nullable()->constrained()->onDelete('set null');
            $table->string('external_id')->nullable();
            $table->text('description');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('EUR');
            $table->date('transaction_date');
            $table->date('value_date')->nullable();
            $table->enum('type', ['credit', 'debit']);
            $table->enum('status', ['pending', 'completed', 'failed'])->default('completed');
            $table->string('merchant_name')->nullable();
            $table->string('merchant_id')->nullable();
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_imported')->default(false);
            $table->boolean('ai_analyzed')->default(false);
            $table->timestamps();

            $table->index(['user_id', 'transaction_date']);
            $table->index(['bank_account_id', 'transaction_date']);
            $table->index('external_id');
            $table->index('type');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
}; 