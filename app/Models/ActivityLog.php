<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'outlet_id', 'action', 'description', 'subject_type', 'subject_id', 'properties', 'ip_address'];

    // Label & grup untuk filter di halaman Monitoring
    public const ACTIONS = [
        'login' => 'Login',
        'logout' => 'Logout',
        'shift.open' => 'Buka Shift',
        'shift.close' => 'Tutup Shift',
        'transaction.create' => 'Transaksi',
        'transaction.void' => 'Void Transaksi',
        'stock.restock' => 'Restock',
        'stock.import' => 'Import Stok',
        'stock.return' => 'Retur',
        'stock.opname' => 'Stock Opname',
        'stock.transfer' => 'Transfer Stok',
        'stock.export' => 'Export Stok',
        'product.create' => 'Produk Baru',
        'product.update' => 'Ubah Produk',
        'product.delete' => 'Hapus Produk',
        'product.import' => 'Import Produk',
        'variant.update' => 'Ubah Varian',
        'staff.update' => 'Ubah Karyawan',
        'settings.update' => 'Ubah Pengaturan',
        'outlet.update' => 'Ubah Outlet',
    ];

    protected function casts(): array
    {
        return [
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function actionLabel(): string
    {
        return self::ACTIONS[$this->action] ?? $this->action;
    }
}
