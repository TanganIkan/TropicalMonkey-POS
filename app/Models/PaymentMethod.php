<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class PaymentMethod extends Model
{
    protected $fillable = ['code', 'name', 'receipt_label', 'tax_rate', 'is_cash', 'is_active', 'sort_order', 'color'];

    protected function casts(): array
    {
        return [
            'tax_rate' => 'float',
            'is_cash' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Semua metode (termasuk nonaktif, untuk label transaksi lama), di-key per code.
     */
    public static function allByCode(): Collection
    {
        return once(fn() => static::orderBy('sort_order')->get()->keyBy('code'));
    }

    public static function active(): Collection
    {
        return static::allByCode()->where('is_active', true)->values();
    }

    public static function labelFor(string $code): string
    {
        return static::allByCode()->get($code)?->name ?? ($code === 'split' ? 'Split' : strtoupper($code));
    }
}
