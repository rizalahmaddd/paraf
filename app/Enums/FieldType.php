<?php

namespace App\Enums;

enum FieldType: string
{
    case Signature = 'SIGNATURE';
    case Initial = 'INITIAL';
    case Date = 'DATE';
    case Name = 'NAME';
    case Text = 'TEXT';
    case Checkbox = 'CHECKBOX';

    public function label(): string
    {
        return match ($this) {
            self::Signature => 'Tanda tangan',
            self::Initial => 'Paraf',
            self::Date => 'Tanggal',
            self::Name => 'Nama signer',
            self::Text => 'Teks',
            self::Checkbox => 'Checkbox',
        };
    }

    public function isImage(): bool
    {
        return $this === self::Signature || $this === self::Initial;
    }

    /**
     * Date and name are filled by the server at submit time, never by the signer.
     */
    public function isAutoFilled(): bool
    {
        return $this === self::Date || $this === self::Name;
    }
}
