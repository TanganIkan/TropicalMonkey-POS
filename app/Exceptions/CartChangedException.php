<?php

namespace App\Exceptions;

/**
 * Isi keranjang berbeda dengan data terbaru (harga berubah / barang tidak
 * tersedia lagi). $cart berisi keranjang yang sudah diperbarui.
 */
class CartChangedException extends PosException
{
    public function __construct(public readonly array $cart, public readonly array $changes)
    {
        parent::__construct(
            implode(' ', $changes) . ' Silakan cek ulang total belanja sebelum membayar.',
            'Keranjang Diperbarui',
            'warning',
        );
    }
}
