<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->foreignId('outlet_id')->constrained();
            $table->timestamp('opened_at');
            $table->decimal('opening_cash', 15, 2)->default(0); // modal awal kas
            $table->timestamp('closed_at')->nullable();
            $table->decimal('expected_cash', 15, 2)->nullable(); // modal + penjualan tunai
            $table->decimal('closing_cash', 15, 2)->nullable(); // hasil hitung kas fisik
            $table->decimal('cash_difference', 15, 2)->nullable(); // closing - expected
            $table->text('closing_note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'closed_at']);
            $table->index(['outlet_id', 'opened_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
