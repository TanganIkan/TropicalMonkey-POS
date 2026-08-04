<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    protected $fillable = [
        'name',
        'address',
        'phone',
        'is_active',
    ];

    // Relasi: Satu outlet memiliki banyak produk
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}