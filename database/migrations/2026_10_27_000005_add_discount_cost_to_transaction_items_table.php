<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            // subtotal = price * quantity - discount_amount
            $table->decimal('discount_amount', 15, 2)->default(0)->after('quantity'); // diskon per item
            $table->decimal('order_discount_share', 15, 2)->default(0)->after('subtotal'); // bagian diskon total belanja
            $table->decimal('cost_price', 15, 2)->default(0)->after('price'); // HPP per unit saat terjual
        });

        // Transaksi lama: HPP diisi dari HPP produk saat ini (data terbaik yang tersedia)
        DB::table('transaction_items')->update([
            'cost_price' => DB::raw('COALESCE((SELECT p.cost_price FROM products p WHERE p.id = transaction_items.product_id), 0)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('transaction_items', function (Blueprint $table) {
            $table->dropColumn(['discount_amount', 'order_discount_share', 'cost_price']);
        });
    }
};
