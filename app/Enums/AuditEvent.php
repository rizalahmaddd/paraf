<?php

namespace App\Enums;

enum AuditEvent: string
{
    case Created = 'CREATED';
    case Sent = 'SENT';
    case Viewed = 'VIEWED';
    case PasscodeVerified = 'PASSCODE_VERIFIED';
    case PasscodeFailed = 'PASSCODE_FAILED';
    case Signed = 'SIGNED';
    case Declined = 'DECLINED';
    case Completed = 'COMPLETED';
    case Voided = 'VOIDED';
    case Expired = 'EXPIRED';
    case ReminderSent = 'REMINDER_SENT';
    case InvitationResent = 'INVITATION_RESENT';
    case ContactUpdated = 'CONTACT_UPDATED';
    case ProcessingFailed = 'PROCESSING_FAILED';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Dokumen diunggah',
            self::Sent => 'Dokumen dikirim',
            self::Viewed => 'Dokumen dibuka',
            self::PasscodeVerified => 'Passcode terverifikasi',
            self::PasscodeFailed => 'Passcode salah',
            self::Signed => 'Ditandatangani',
            self::Declined => 'Ditolak',
            self::Completed => 'Dokumen disegel',
            self::Voided => 'Dokumen dibatalkan',
            self::Expired => 'Dokumen kedaluwarsa',
            self::ReminderSent => 'Pengingat dikirim',
            self::InvitationResent => 'Undangan dikirim ulang',
            self::ContactUpdated => 'Kontak signer diubah',
            self::ProcessingFailed => 'Pemrosesan PDF gagal',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Created => 'file-up',
            self::Sent => 'send',
            self::Viewed => 'eye',
            self::PasscodeVerified => 'key-round',
            self::PasscodeFailed => 'shield-alert',
            self::Signed => 'pen-line',
            self::Declined => 'circle-x',
            self::Completed => 'badge-check',
            self::Voided => 'ban',
            self::Expired => 'clock-alert',
            self::ReminderSent => 'bell-ring',
            self::InvitationResent => 'mail-plus',
            self::ContactUpdated => 'user-pen',
            self::ProcessingFailed => 'triangle-alert',
        };
    }
}
