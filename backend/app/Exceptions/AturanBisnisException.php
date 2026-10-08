<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Pelanggaran aturan bisnis (stok tidak cukup, transisi status tidak sah, dsb.).
 * Dirender sebagai 422 + code ATURAN_BISNIS, bukan 500.
 */
class AturanBisnisException extends RuntimeException
{
    public function __construct(string $pesan, protected string $kodeAturan = 'ATURAN_BISNIS')
    {
        parent::__construct($pesan);
    }

    public function kodeAturan(): string
    {
        return $this->kodeAturan;
    }
}
