<?php

namespace App\Enums;

enum SigningOrderMode: string
{
    case Parallel = 'PARALLEL';
    case Sequential = 'SEQUENTIAL';

    public function label(): string
    {
        return match ($this) {
            self::Parallel => 'Paralel',
            self::Sequential => 'Berurutan',
        };
    }
}
