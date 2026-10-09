<?php

namespace App\Events;

use App\Models\Document;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired once the last signer has signed; the sealed PDF is produced afterwards in the queue.
 */
class DocumentCompleted
{
    use Dispatchable;

    public function __construct(public Document $document) {}
}
