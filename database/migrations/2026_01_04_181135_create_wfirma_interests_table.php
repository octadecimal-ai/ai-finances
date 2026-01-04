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
        Schema::create('wfirma_interests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            
            // Identyfikatory
            $table->string('wfirma_id')->index();
            $table->string('employee_id')->nullable();
            
            // Informacje o rozliczeniu
            $table->string('type')->nullable();
            $table->string('period')->nullable()->index(); // YYYY-MM
            $table->string('zus_type')->nullable()->index(); // sp, zp, fp, fgsp, fgzp, fgfp
            $table->string('declaration_number')->nullable();
            
            // Daty
            $table->date('date')->nullable()->index();
            $table->date('due_date')->nullable();
            $table->date('payment_date')->nullable();
            
            // Kwoty i status
            $table->decimal('amount', 15, 2)->nullable();
            $table->string('currency', 3)->default('PLN');
            $table->string('status')->nullable();
            $table->boolean('paid')->default(false)->index();
            
            // Dodatkowe
            $table->text('description')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('synced_at')->nullable();
            
            $table->timestamps();
            
            // Indeksy
            $table->index(['user_id', 'wfirma_id']);
            $table->index(['user_id', 'period']);
            $table->index(['user_id', 'paid']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wfirma_interests');
    }
};
