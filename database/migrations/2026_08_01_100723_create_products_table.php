<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('sku')->unique()->nullable(); // Nullable karena SKU bisa pindah ke varian
            $table->string('name');
            $table->string('brand')->nullable(); // Tambahan merek
            $table->string('image')->nullable(); // Tambahan gambar produk
            $table->decimal('cost_price', 15, 2)->default(0);
            $table->decimal('sell_price', 15, 2)->default(0);
            $table->boolean('has_variants')->default(false); // Penanda apakah produk ini punya ukuran/warna
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
