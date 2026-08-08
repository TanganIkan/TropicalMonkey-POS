<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['product_id', 'sku', 'size', 'color', 'barcode'])]
class ProductVariant extends Model
{
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}