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
        Schema::create('wfirma_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Identyfikatory
            $table->string('wfirma_id')->index();
            $table->string('term_group_id')->nullable();
            $table->string('contractor_id')->nullable();
            $table->string('invoice_id')->nullable()->index();
            $table->string('expense_id')->nullable()->index();
            $table->string('income_id')->nullable()->index();
            
            // Informacje o terminie
            $table->date('date')->nullable()->index();
            $table->time('time')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->nullable();
            
            // Przypomnienia
            $table->boolean('reminder')->default(false);
            $table->integer('reminder_minutes')->nullable();
            
            // Synchronizacja
            $table->json('metadata')->nullable();
            $table->timestamp('synced_at')->nullable();
            
            $table->timestamps();
            
            // Indeksy
            $table->index(['user_id', 'wfirma_id']);
            $table->index(['user_id', 'date']);
            $table->index(['user_id', 'invoice_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wfirma_terms');
    }
};
