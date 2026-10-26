<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            // subtotal = jumlah item setelah diskon per item
            $table->decimal('discount_amount', 15, 2)->default(0)->after('subtotal'); // diskon total belanja
            $table->string('status')->default('completed')->after('change_amount'); // completed | void
            $table->timestamp('voided_at')->nullable()->after('status');
            $table->foreignId('voided_by')->nullable()->after('voided_at')->constrained('users');
            $table->string('void_reason')->nullable()->after('voided_by');
            $table->foreignId('shift_id')->nullable()->after('user_id')->constrained('shifts')->nullOnDelete();

            $table->index(['created_at', 'outlet_id']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['voided_by']);
            $table->dropForeign(['shift_id']);
            $table->dropIndex(['created_at', 'outlet_id']);
            $table->dropIndex(['status']);
            $table->dropColumn(['discount_amount', 'status', 'voided_at', 'voided_by', 'void_reason', 'shift_id']);
        });
    }
};
