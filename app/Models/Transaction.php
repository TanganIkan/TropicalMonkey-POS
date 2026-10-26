<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * subtotal        = jumlah item setelah diskon per item
 * discount_amount = diskon total belanja
 * tax_amount      = pajak (mis. 3% kartu luar), dihitung per bagian pembayaran
 * total           = subtotal - discount_amount + tax_amount
 */
#[Fillable(['order_id', 'outlet_id', 'user_id', 'shift_id', 'subtotal', 'discount_amount', 'tax_amount', 'total', 'payment_method', 'cash_received', 'change_amount', 'status', 'voided_at', 'voided_by', 'void_reason'])]
class Transaction extends Model
{
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_VOID = 'void';

    protected $attributes = [
        'status' => self::STATUS_COMPLETED,
        'discount_amount' => 0,
    ];

    protected function casts(): array
    {
        return [
            'voided_at' => 'datetime',
        ];
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function voidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(TransactionPayment::class);
    }

    public function scopeCompleted(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_COMPLETED);
    }

    public function isVoid(): bool
    {
        return $this->status === self::STATUS_VOID;
    }

    /**
     * Penjualan bersih (tanpa pajak) = pendapatan toko dari transaksi ini.
     */
    public function netSales(): float
    {
        return $this->subtotal - $this->discount_amount;
    }

    /**
     * Total diskon (per item + total belanja).
     */
    public function totalDiscount(): float
    {
        return $this->items->sum('discount_amount') + $this->discount_amount;
    }
}
