<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu transaksi bisa dibayar dengan beberapa metode (split payment)
        Schema::create('transaction_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->cascadeOnDelete();
            $table->string('payment_method'); // payment_methods.code
            $table->decimal('amount', 15, 2); // bagian belanja yang dibayar (sebelum pajak)
            $table->decimal('tax_rate', 5, 2)->default(0); // tarif pajak saat transaksi
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('cash_received', 15, 2)->nullable();
            $table->decimal('change_amount', 15, 2)->nullable();
            $table->timestamps();

            $table->index('payment_method');
        });

        // Transaksi lama = satu pembayaran penuh
        DB::table('transactions')->orderBy('id')->each(function ($trx) {
            DB::table('transaction_payments')->insert([
                'transaction_id' => $trx->id,
                'payment_method' => $trx->payment_method,
                'amount' => $trx->subtotal,
                'tax_rate' => $trx->tax_amount > 0 && $trx->subtotal > 0 ? round($trx->tax_amount / $trx->subtotal * 100, 2) : 0,
                'tax_amount' => $trx->tax_amount,
                'cash_received' => $trx->cash_received,
                'change_amount' => $trx->change_amount,
                'created_at' => $trx->created_at,
                'updated_at' => $trx->updated_at,
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transaction_payments');
    }
};
