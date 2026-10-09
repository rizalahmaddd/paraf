<?php

namespace App\Notifications;

use App\Models\Signer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentDeclinedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Signer $signer) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $document = $this->signer->document;

        return (new MailMessage)
            ->subject("Dokumen ditolak: {$document->title}")
            ->greeting("Halo {$notifiable->name},")
            ->line("{$this->signer->name} menolak menandatangani \"{$document->title}\".")
            ->line('Alasan: '.$this->signer->decline_reason)
            ->line('Semua tautan penandatanganan untuk dokumen ini sudah dinonaktifkan.')
            ->action('Lihat Dokumen', route('documents.show', $document));
    }

    /**
     * @return array{icon: string, color: string, message: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'icon' => 'circle-x',
            'color' => 'rose',
            'message' => "{$this->signer->name} menolak \"{$this->signer->document->title}\": {$this->signer->decline_reason}",
            'url' => route('documents.show', $this->signer->document_id),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
