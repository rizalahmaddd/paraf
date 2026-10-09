<?php

namespace App\Events;

use App\Models\Document;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Contracts\Broadcasting\ShouldRescue;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Goes to the owner's private channel only: documents belong to one tenant, unlike the
 * starter's shared "dashboard" channel.
 */
class DocumentChanged implements ShouldBroadcastNow, ShouldRescue
{
    use Dispatchable;

    public function __construct(public Document $document) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('App.Models.User.'.$this->document->user_id)];
    }

    public function broadcastAs(): string
    {
        return 'document.changed';
    }

    /**
     * @return array{id: string, status: string}
     */
    public function broadcastWith(): array
    {
        return [
            'id' => $this->document->id,
            'status' => $this->document->status->value,
        ];
    }
}
