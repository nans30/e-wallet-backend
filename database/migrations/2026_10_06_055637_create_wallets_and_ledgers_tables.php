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
        // 1. Wallets
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('currency', 3)->default('IDR');
            $table->unsignedBigInteger('balance')->default(0); // in smallest unit (e.g. IDR)
            $table->timestamps();

            $table->unique(['user_id', 'currency']);
        });

        // 2. Transactions (Header/Intent)
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_id')->unique(); // For idempotency and tracking
            $table->foreignId('user_id')->constrained(); // Initiator
            $table->enum('type', ['TOPUP', 'TRANSFER', 'WITHDRAWAL']);
            $table->unsignedBigInteger('amount');
            $table->enum('status', ['PENDING', 'SUCCESS', 'FAILED'])->default('PENDING');
            $table->string('description')->nullable();
            $table->timestamps();
        });

        // 3. Ledgers (Double-entry / Mutations)
        Schema::create('ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->enum('entry_type', ['DEBIT', 'CREDIT']); // DEBIT = out/decrease, CREDIT = in/increase
            $table->unsignedBigInteger('amount');
            $table->unsignedBigInteger('balance_before');
            $table->unsignedBigInteger('balance_after');
            $table->timestamps();

            $table->index(['wallet_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledgers');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('wallets');
    }
};
