<?php

namespace App\Notifications;

use App\Models\Signer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class DocumentSignedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * @param  list<string>  $nextSignerNames  sequential + manual distribution: whose link the owner should share now
     */
    public function __construct(public Signer $signer, public array $nextSignerNames = []) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /**
     * @return array{icon: string, color: string, message: string, url: string}
     */
    public function toArray(object $notifiable): array
    {
        $message = "{$this->signer->name} menandatangani \"{$this->signer->document->title}\".";

        if ($this->nextSignerNames !== []) {
            $message .= ' Giliran berikutnya: '.implode(', ', $this->nextSignerNames).'. Bagikan tautannya.';
        }

        return [
            'icon' => 'pen-line',
            'color' => 'emerald',
            'message' => $message,
            'url' => route('documents.show', $this->signer->document_id),
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
