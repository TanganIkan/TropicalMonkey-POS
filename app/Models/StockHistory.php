<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockHistory extends Model
{
    // Jenis mutasi stok. quantity selalu positif; arah ditentukan oleh jenisnya.
    public const TYPES = [
        'initial' => ['label' => 'Stok Awal', 'direction' => 'in'],
        'restock' => ['label' => 'Restock', 'direction' => 'in'],
        'transfer_in' => ['label' => 'Transfer Masuk', 'direction' => 'in'],
        'adjustment_in' => ['label' => 'Penyesuaian (+)', 'direction' => 'in'],
        'void' => ['label' => 'Void Transaksi', 'direction' => 'in'],
        'sale' => ['label' => 'Penjualan', 'direction' => 'out'],
        'return' => ['label' => 'Retur', 'direction' => 'out'],
        'transfer_out' => ['label' => 'Transfer Keluar', 'direction' => 'out'],
        'adjustment_out' => ['label' => 'Penyesuaian (-)', 'direction' => 'out'],
    ];

    protected $fillable = [
        'product_id',
        'product_variant_id', // Mencatat ukuran/warna yang masuk
        'outlet_id',          // Mencatat barang masuk ke cabang mana
        'user_id',            // Siapa yang melakukan
        'quantity',           // Jumlah barang (selalu positif)
        'stock_before',
        'stock_after',
        'type',               // Lihat TYPES
        'nota_number',        // Nomor referensi: nota supplier / order transaksi / kode transfer
        'note',               // Alasan retur / penyesuaian
    ];

    public static function typesFor(string $direction): array
    {
        return array_keys(array_filter(self::TYPES, fn($t) => $t['direction'] === $direction));
    }

    public function isIncoming(): bool
    {
        return (self::TYPES[$this->type]['direction'] ?? 'in') === 'in';
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type]['label'] ?? $this->type;
    }

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
