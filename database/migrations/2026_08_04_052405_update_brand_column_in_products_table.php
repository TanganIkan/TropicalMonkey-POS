<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('brand');

            $table->foreignId('brand_id')->nullable()->after('category_id')->constrained('brands')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // Rollback: kembalikan ke kondisi semula jika di-rollback
            $table->dropForeign(['brand_id']);
            $table->dropColumn('brand_id');
            $table->string('brand')->nullable()->after('category_id');
        });
    }
};
