<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_histories', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('outlet_id')->constrained()->nullOnDelete(); // siapa yang input
            $table->integer('stock_before')->nullable()->after('quantity');
            $table->integer('stock_after')->nullable()->after('stock_before');
            $table->string('note')->nullable()->after('nota_number'); // alasan retur/penyesuaian

            $table->index(['outlet_id', 'created_at']);
        });

        // Jenis lama in/out -> jenis baru. 'in' dulu dipakai untuk stok awal & restock.
        DB::table('stock_histories')->where('type', 'in')->update(['type' => 'restock']);
        DB::table('stock_histories')->where('type', 'out')->where('nota_number', 'like', 'TRX-%')->update(['type' => 'sale']);
        // Retur lama menyimpan alasan di kolom nota_number
        DB::table('stock_histories')->where('type', 'out')->update([
            'type' => 'return',
            'note' => DB::raw('nota_number'),
            'nota_number' => null,
        ]);
    }

    public function down(): void
    {
        DB::table('stock_histories')->where('type', 'return')->update(['nota_number' => DB::raw('note')]);
        DB::table('stock_histories')->whereIn('type', ['sale', 'return', 'adjustment_out', 'transfer_out'])->update(['type' => 'out']);
        DB::table('stock_histories')->where('type', '!=', 'out')->update(['type' => 'in']);

        Schema::table('stock_histories', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropIndex(['outlet_id', 'created_at']);
            $table->dropColumn(['user_id', 'stock_before', 'stock_after', 'note']);
        });
    }
};
