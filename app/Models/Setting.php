<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan toko berbentuk key-value (diatur owner di halaman Pengaturan).
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public const DEFAULTS = [
        'receipt_footer' => "Thank you for your visit!\nItems purchased cannot be returned or exchanged.",
        'low_stock_threshold' => '5',
        'size_options' => '["S","M","L","XL","XXL"]',
        'logo_primary' => null,
        'logo_secondary' => null,
    ];

    protected const CACHE_KEY = 'settings.all';

    public static function get(string $key, $default = null)
    {
        $all = Cache::rememberForever(self::CACHE_KEY, fn() => static::pluck('value', 'key')->all());

        return $all[$key] ?? $default ?? self::DEFAULTS[$key] ?? null;
    }

    public static function set(string $key, $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
        Cache::forget(self::CACHE_KEY);
    }

    public static function lowStockThreshold(): int
    {
        return (int) static::get('low_stock_threshold');
    }

    /**
     * @return list<string>
     */
    public static function sizeOptions(): array
    {
        return json_decode(static::get('size_options'), true) ?: [];
    }
}
