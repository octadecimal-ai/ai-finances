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
        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('transaction_id')->nullable()->after('user_id')->constrained('transactions')->onDelete('set null');
            $table->decimal('match_score', 5, 2)->nullable()->after('transaction_id')->comment('Wynik dopasowania (0-100)');
            $table->timestamp('matched_at')->nullable()->after('match_score')->comment('Data dopasowania');
            
            $table->index('transaction_id');
            $table->index('match_score');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['transaction_id']);
            $table->dropIndex(['transaction_id']);
            $table->dropIndex(['match_score']);
            $table->dropColumn(['transaction_id', 'match_score', 'matched_at']);
        });
    }
};
