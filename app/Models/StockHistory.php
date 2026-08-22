<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockHistory extends Model
{
    protected $fillable = [
        'product_id',
        'product_variant_id', // Mencatat ukuran/warna yang masuk
        'outlet_id',          // Mencatat barang masuk ke cabang mana
        'quantity',           // Jumlah barang masuk (atau keluar)
        'type',               // Status 'in' (masuk) atau 'out' (terjual/rusak)
        'nota_number'         // Nomor referensi dokumen supplier
    ];

    // Relasi untuk memudahkan penarikan data laporan nantinya
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }
}