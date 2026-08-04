<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['category_id', 'sku', 'barcode', 'name', 'brand', 'cost_price', 'sell_price', 'has_variants', 'is_active'])]
class Product extends Model
{
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function stocks(): HasMany
    {
        return $this->hasMany(Stock::class);
    }

    public function transactionItems(): HasMany
    {
        return $this->hasMany(TransactionItem::class);
    }
}