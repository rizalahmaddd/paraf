<?php

namespace App\Notifications;

use App\Models\Signer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SignatureRequestNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Signer $signer, public bool $isReminder = false) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $document = $this->signer->document;
        $sender = $document->user->name;

        $message = (new MailMessage)
            ->subject(($this->isReminder ? 'Pengingat: ' : '')."{$sender} meminta tanda tangan Anda: {$document->title}")
            ->greeting("Halo {$this->signer->name},");

        $message->line($this->isReminder
            ? "Dokumen \"{$document->title}\" dari {$sender} masih menunggu tanda tangan Anda."
            : "{$sender} mengirim dokumen \"{$document->title}\" untuk Anda tinjau dan tandatangani.");

        if ($document->description) {
            $message->line('Pesan dari pengirim: '.$document->description);
        }

        if ($this->signer->hasPasscode()) {
            $message->line('Dokumen ini dilindungi passcode 6 digit. Minta passcode kepada pengirim bila Anda belum menerimanya.');
        }

        return $message
            ->action('Tinjau & Tanda Tangani Dokumen', (string) $this->signer->signingUrl())
            ->line('Tautan berlaku sampai '.$document->expires_at?->timezone('Asia/Jakarta')->isoFormat('D MMMM Y, HH:mm').' WIB. Anda tidak perlu membuat akun.')
            ->line('Jangan teruskan email ini: tautannya khusus untuk Anda.');
    }
}
