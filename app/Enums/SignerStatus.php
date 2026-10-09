<?php

namespace App\Enums;

enum SignerStatus: string
{
    case Pending = 'PENDING';
    case Viewed = 'VIEWED';
    case Signed = 'SIGNED';
    case Declined = 'DECLINED';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Belum dibuka',
            self::Viewed => 'Sudah dibuka',
            self::Signed => 'Sudah tanda tangan',
            self::Declined => 'Menolak',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'slate',
            self::Viewed => 'sky',
            self::Signed => 'emerald',
            self::Declined => 'rose',
        };
    }

    public function hasActed(): bool
    {
        return $this === self::Signed || $this === self::Declined;
    }
}
