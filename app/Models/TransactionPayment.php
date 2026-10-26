<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionPayment extends Model
{
    protected $fillable = ['transaction_id', 'payment_method', 'amount', 'tax_rate', 'tax_amount', 'cash_received', 'change_amount'];

    protected function casts(): array
    {
        return [
            'amount' => 'float',
            'tax_rate' => 'float',
            'tax_amount' => 'float',
            'cash_received' => 'float',
            'change_amount' => 'float',
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function method(): BelongsTo
    {
        return $this->belongsTo(PaymentMethod::class, 'payment_method', 'code');
    }

    /**
     * Uang yang ditagihkan untuk bagian ini (belanja + pajak).
     */
    public function total(): float
    {
        return $this->amount + $this->tax_amount;
    }
}
