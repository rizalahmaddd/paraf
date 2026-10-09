<?php

namespace App\Notifications;

use App\Models\Signer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentVoidedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Signer $signer) {}

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

        return (new MailMessage)
            ->subject("Dokumen dibatalkan: {$document->title}")
            ->greeting("Halo {$this->signer->name},")
            ->line("{$document->user->name} telah membatalkan dokumen \"{$document->title}\". Anda tidak perlu menandatanganinya lagi dan tautan sebelumnya sudah tidak aktif.");
    }
}
