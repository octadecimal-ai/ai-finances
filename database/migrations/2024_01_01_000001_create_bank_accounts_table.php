<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('bank_name');
            $table->string('account_name');
            $table->string('account_number')->nullable();
            $table->string('iban')->nullable();
            $table->string('currency', 3)->default('EUR');
            $table->decimal('balance', 15, 2)->default(0);
            $table->timestamp('last_sync_at')->nullable();
            $table->string('provider'); // nordigen, revolut, etc.
            $table->string('provider_account_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('sync_enabled')->default(true);
            $table->timestamps();

            $table->index(['user_id', 'provider']);
            $table->index('provider_account_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bank_accounts');
    }
}; 