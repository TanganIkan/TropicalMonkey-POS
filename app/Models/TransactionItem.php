<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * subtotal             = price * quantity - discount_amount (yang tampil di struk)
 * order_discount_share = bagian diskon total belanja yang dibebankan ke item ini
 * net                  = subtotal - order_discount_share (dipakai laporan per brand & laba)
 */
#[Fillable([
    'transaction_id',
    'product_id',
    'product_variant_id',
    'product_name',
    'price',
    'cost_price',
    'quantity',
    'discount_amount',
    'subtotal',
    'order_discount_share',
])]
class TransactionItem extends Model
{
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function netAmount(): float
    {
        return $this->subtotal - $this->order_discount_share;
    }
}
