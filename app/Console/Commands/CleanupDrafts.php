<?php

namespace App\Console\Commands;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\DocumentWorkflow;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('paraf:cleanup-drafts')]
#[Description('Delete abandoned drafts (and their files) older than the retention period')]
class CleanupDrafts extends Command
{
    public function handle(DocumentWorkflow $workflow): int
    {
        $deleted = 0;

        Document::query()
            ->where('status', DocumentStatus::Draft)
            ->notTemplates()
            ->where('updated_at', '<', now()->subDays(config('paraf.draft_retention_days')))
            ->chunkById(100, function ($documents) use ($workflow, &$deleted) {
                foreach ($documents as $document) {
                    $workflow->deleteDraft($document);
                    $deleted++;
                }
            });

        // Leftovers from crashed jobs; normal runs delete their own temp files.
        foreach (File::glob(storage_path('app/paraf-tmp/*')) as $file) {
            if (filemtime($file) < now()->subDay()->getTimestamp()) {
                File::delete($file);
            }
        }

        $this->components->info("Draft dihapus: {$deleted}");

        return self::SUCCESS;
    }
}
