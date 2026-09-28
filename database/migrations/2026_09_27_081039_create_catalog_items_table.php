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
        Schema::create('catalog_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('catalog_number', 30)->unique();
            $table->string('type', 16);
            $table->string('sku', 64)->nullable();
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->decimal('unit_price', 14, 2);
            $table->char('currency', 3)->default('GHS');
            $table->string('status', 16)->default('active');
            $table->timestamps();

            $table->unique(['owner_id', 'sku']);
            $table->index(['owner_id', 'type', 'status']);
            $table->index(['owner_id', 'name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catalog_items');
    }
};
