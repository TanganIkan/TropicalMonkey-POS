<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'sku', 'size', 'color', 'barcode', 'sell_price', 'cost_price', 'is_active'])]
class ProductVariant extends Model
{
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sell_price' => 'float',
            'cost_price' => 'float',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Harga varian; kosong = ikut harga produk induk.
     */
    public function effectiveSellPrice(): float
    {
        return $this->sell_price ?? (float) $this->product->sell_price;
    }

    public function effectiveCostPrice(): float
    {
        return $this->cost_price ?? (float) $this->product->cost_price;
    }

    public function label(): string
    {
        return "{$this->size} - {$this->color}";
    }
}
