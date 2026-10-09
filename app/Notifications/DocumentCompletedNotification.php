<?php

namespace App\Notifications;

use App\Models\Document;
use App\Models\Signer;
use App\Models\User;
use App\Services\DocumentStorage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Sent to the owner (mail + bell) and to every signer with an email address (mail only).
 */
class DocumentCompletedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    private const MAX_ATTACHMENT_BYTES = 10 * 1024 * 1024;

    public function __construct(public Document $document, public ?Signer $signer = null) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? ['mail', 'database', 'broadcast'] : ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = $this->signer?->name ?? $notifiable->name ?? '';
        $url = $this->signer ? (string) $this->signer->signingUrl() : route('documents.show', $this->document);

        $message = (new MailMessage)
            ->subject("Selesai ditandatangani: {$this->document->title}")
            ->greeting("Halo {$name},")
            ->line("Semua pihak telah menandatangani \"{$this->document->title}\". Dokumen final sudah disegel bersama lembar audit trail.")
            ->line('SHA-256 dokumen final: '.$this->document->completed_hash_sha256)
            ->action('Unduh Dokumen Final', $url)
            ->line('Keaslian dokumen bisa dicek kapan saja di '.route('verify.show', $this->document).'.');

        $storage = app(DocumentStorage::class);

        if ($this->document->completed_pdf_path && $this->document->file_size <= self::MAX_ATTACHMENT_BYTES && $storage->exists($this->document->completed_pdf_path)) {
            $message->attachData(
                $storage->get($this->document->completed_pdf_path),
                Str::slug($this->document->title).'-signed.pdf',
                ['mime' => 'application/pdf'],
            );
        }

        return $message;
    }

    /**
     * @return array{icon: string, color: string, message: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'icon' => 'badge-check',
            'color' => 'emerald',
            'message' => "\"{$this->document->title}\" selesai ditandatangani semua pihak.",
            'url' => route('documents.show', $this->document),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
