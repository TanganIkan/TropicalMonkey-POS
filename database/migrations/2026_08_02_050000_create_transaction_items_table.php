<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('transaction_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->foreignId('product_variant_id')->nullable()->constrained();
            $table->string('product_name');
            $table->decimal('price', 15, 2);
            $table->integer('quantity');
            $table->decimal('subtotal', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_items');
    }
};