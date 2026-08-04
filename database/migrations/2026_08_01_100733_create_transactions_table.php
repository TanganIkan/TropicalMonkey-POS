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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('order_id')->unique(); // Format misal: #ORD-2941
            $table->foreignId('outlet_id')->constrained(); // Tahu transaksi ini dari toko mana
            $table->foreignId('user_id')->constrained(); // Kasir yang melayani
            $table->decimal('subtotal', 15, 2);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->string('payment_method'); // Tunai, Kartu, dll.
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
