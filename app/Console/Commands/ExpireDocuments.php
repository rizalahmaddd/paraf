<?php

namespace App\Console\Commands;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\DocumentWorkflow;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('paraf:expire-documents')]
#[Description('Mark in-progress documents past their deadline as EXPIRED')]
class ExpireDocuments extends Command
{
    public function handle(DocumentWorkflow $workflow): int
    {
        $expired = 0;

        Document::query()
            ->whereIn('status', DocumentStatus::inProgress())
            ->where('expires_at', '<', now())
            ->chunkById(100, function ($documents) use ($workflow, &$expired) {
                foreach ($documents as $document) {
                    $expired += (int) $workflow->expire($document);
                }
            });

        $this->components->info("Dokumen kedaluwarsa: {$expired}");

        return self::SUCCESS;
    }
}
