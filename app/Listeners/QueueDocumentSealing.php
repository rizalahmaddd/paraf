<?php

namespace App\Listeners;

use App\Events\DocumentCompleted;
use App\Jobs\ProcessCompletedDocumentPdf;

class QueueDocumentSealing
{
    public function handle(DocumentCompleted $event): void
    {
        ProcessCompletedDocumentPdf::dispatch($event->document);
    }
}
