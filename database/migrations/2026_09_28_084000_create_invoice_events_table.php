<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('from_status', 24);
            $table->string('to_status', 24);
            $table->string('note', 500)->nullable();
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['invoice_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_events');
    }
};
