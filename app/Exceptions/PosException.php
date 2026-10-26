<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Error bisnis yang pesannya aman ditampilkan ke user (lewat toast notifikasi).
 */
class PosException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $title = 'Gagal!',
        public readonly string $icon = 'error',
    ) {
        parent::__construct($message);
    }

    public function toNotify(): array
    {
        return [
            'title' => $this->title,
            'text' => $this->getMessage(),
            'icon' => $this->icon,
        ];
    }
}
