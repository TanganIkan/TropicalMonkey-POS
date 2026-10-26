<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // disimpan di transactions.payment_method
            $table->string('name');
            $table->string('receipt_label');
            $table->decimal('tax_rate', 5, 2)->default(0); // persen pajak, mis. 3.00
            $table->boolean('is_cash')->default(false); // tunai: ada uang diterima & kembalian
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('color', 7)->default('#9ca3af'); // warna di grafik dashboard
            $table->timestamps();
        });

        // Metode bayar yang sebelumnya ditulis langsung di kode
        $now = now();
        DB::table('payment_methods')->insert([
            ['code' => 'tunai', 'name' => 'Tunai', 'receipt_label' => 'CASH', 'tax_rate' => 0, 'is_cash' => true, 'sort_order' => 1, 'color' => '#e5e7eb', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'qris', 'name' => 'QRIS', 'receipt_label' => 'QRIS', 'tax_rate' => 0, 'is_cash' => false, 'sort_order' => 2, 'color' => '#111827', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'kartu_lokal', 'name' => 'Kartu Lokal', 'receipt_label' => 'DEBIT/CREDIT CARD', 'tax_rate' => 0, 'is_cash' => false, 'sort_order' => 3, 'color' => '#4b5563', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['code' => 'kartu_non_lokal', 'name' => 'Kartu Non-Lokal', 'receipt_label' => 'INTERNATIONAL CARD', 'tax_rate' => 3, 'is_cash' => false, 'sort_order' => 4, 'color' => '#9ca3af', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
