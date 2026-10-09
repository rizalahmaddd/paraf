<?php

namespace App\Notifications;

use App\Models\Document;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class DocumentProcessingFailedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Document $document) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->error()
            ->subject("Gagal menyegel PDF: {$this->document->title}")
            ->greeting("Halo {$notifiable->name},")
            ->line("Semua tanda tangan untuk \"{$this->document->title}\" sudah lengkap, tetapi PDF final gagal dibuat setelah 3 kali percobaan.")
            ->line('Tanda tangan tetap tersimpan. Buka dokumen lalu tekan "Proses Ulang PDF".')
            ->action('Proses Ulang PDF', route('documents.show', $this->document));
    }

    /**
     * @return array{icon: string, color: string, message: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'icon' => 'triangle-alert',
            'color' => 'rose',
            'message' => "PDF final \"{$this->document->title}\" gagal dibuat. Proses ulang dari halaman dokumen.",
            'url' => route('documents.show', $this->document),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
