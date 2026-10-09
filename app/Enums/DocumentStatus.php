<?php

namespace App\Enums;

enum DocumentStatus: string
{
    case Draft = 'DRAFT';
    case WaitingForSignatures = 'WAITING_FOR_SIGNATURES';
    case PartiallySigned = 'PARTIALLY_SIGNED';
    case Completed = 'COMPLETED';
    case Declined = 'DECLINED';
    case Expired = 'EXPIRED';
    case Voided = 'VOIDED';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::WaitingForSignatures => 'Menunggu tanda tangan',
            self::PartiallySigned => 'Sebagian ditandatangani',
            self::Completed => 'Selesai',
            self::Declined => 'Ditolak',
            self::Expired => 'Kedaluwarsa',
            self::Voided => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'slate',
            self::WaitingForSignatures, self::PartiallySigned => 'sky',
            self::Completed => 'emerald',
            self::Expired => 'amber',
            self::Declined, self::Voided => 'rose',
        };
    }

    public function isInProgress(): bool
    {
        return in_array($this, [self::WaitingForSignatures, self::PartiallySigned], true);
    }

    /**
     * Final states: links only show a status page and nothing can be signed anymore.
     */
    public function isClosed(): bool
    {
        return in_array($this, [self::Completed, self::Declined, self::Expired, self::Voided], true);
    }

    /**
     * @return list<self>
     */
    public static function inProgress(): array
    {
        return [self::WaitingForSignatures, self::PartiallySigned];
    }
}
