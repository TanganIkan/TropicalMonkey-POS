<?php

namespace App\Exceptions;

class InsufficientStockException extends PosException
{
    public function __construct(
        public readonly string $itemName,
        public readonly int $available,
        public readonly int $requested,
    ) {
        $text = $available <= 0
            ? "Stok {$itemName} kosong di outlet ini."
            : "Stok {$itemName} hanya tersisa {$available} unit (diminta {$requested}).";

        parent::__construct(
            $text,
            $available <= 0 ? 'Stok Habis!' : 'Stok Tidak Cukup!',
            $available <= 0 ? 'error' : 'warning',
        );
    }
}
