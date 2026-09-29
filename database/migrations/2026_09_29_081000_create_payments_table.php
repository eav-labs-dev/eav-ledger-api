<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invoice_id')->constrained('invoices')->restrictOnDelete();
            $table->string('payment_number', 34)->unique();
            $table->decimal('amount', 14, 2);
            $table->string('method', 32);
            $table->string('reference', 120)->nullable();
            $table->timestamp('paid_at');
            $table->string('notes', 500)->nullable();
            $table->timestamps();

            $table->unique(['owner_id', 'reference']);
            $table->index(['owner_id', 'invoice_id', 'paid_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
