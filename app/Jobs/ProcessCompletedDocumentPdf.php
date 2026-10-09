<?php

namespace App\Jobs;

use App\Enums\AuditEvent;
use App\Enums\DocumentStatus;
use App\Events\DocumentChanged;
use App\Models\Document;
use App\Models\DocumentField;
use App\Notifications\DocumentCompletedNotification;
use App\Notifications\DocumentProcessingFailedNotification;
use App\Services\CertificateRenderer;
use App\Services\DocumentStorage;
use App\Services\PdfEngine;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Burns every signature and filled value into the original PDF, appends the audit trail
 * certificate and seals the result with a SHA-256 hash.
 */
class ProcessCompletedDocumentPdf implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 600;

    public int $uniqueFor = 900;

    public function __construct(public Document $document) {}

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [15, 60];
    }

    public function uniqueId(): string
    {
        return $this->document->id;
    }

    public function handle(DocumentStorage $storage, PdfEngine $engine, CertificateRenderer $certificates): void
    {
        $document = $this->document->fresh(['signers', 'fields', 'user']);

        if ($document === null || $document->status === DocumentStatus::Completed || ! $document->isAwaitingSeal()) {
            return;
        }

        $temporaryFiles = [];

        try {
            $original = $temporaryFiles[] = $storage->toTemporaryFile($document->original_pdf_path);
            $signed = $temporaryFiles[] = $storage->temporaryPath();
            $certificate = $temporaryFiles[] = $storage->temporaryPath();
            $final = $temporaryFiles[] = $storage->temporaryPath();

            $fields = [];
            foreach ($document->fields->filter(fn (DocumentField $field) => filled($field->field_value)) as $field) {
                $entry = [
                    'page' => $field->page_number,
                    'x' => $field->x_ratio,
                    'y' => $field->y_ratio,
                    'w' => $field->width_ratio,
                    'h' => $field->height_ratio,
                    'type' => $field->field_type->value,
                ];

                if ($field->field_type->isImage()) {
                    $entry['image'] = $temporaryFiles[] = $storage->toTemporaryFile($field->field_value, 'png');
                } else {
                    $entry['value'] = $field->field_value;
                }

                $fields[] = $entry;
            }

            $engine->bake($original, $signed, $fields);
            $signedHash = hash_file('sha256', $signed);
            $completedAt = now();

            File::put($certificate, $certificates->render($document, $signedHash, $completedAt));
            $engine->append($signed, $certificate, $final, $document->title);

            $finalBytes = (string) File::get($final);
            $finalHash = hash('sha256', $finalBytes);
            $path = "documents/{$document->id}/completed.pdf";
            $storage->put($path, $finalBytes);

            DB::transaction(function () use ($document, $path, $signedHash, $finalHash, $completedAt, $finalBytes) {
                $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

                $locked->update([
                    'status' => DocumentStatus::Completed,
                    'completed_at' => $completedAt,
                    'completed_pdf_path' => $path,
                    'signed_hash_sha256' => $signedHash,
                    'completed_hash_sha256' => $finalHash,
                    'processing_failed_at' => null,
                    'processing_error' => null,
                ]);

                $locked->record(AuditEvent::Completed, metadata: [
                    'signed_sha256' => $signedHash,
                    'completed_sha256' => $finalHash,
                    'bytes' => strlen($finalBytes),
                ]);
            });
        } finally {
            File::delete($temporaryFiles);
        }

        $document->refresh();
        DocumentChanged::dispatch($document);

        $document->user->notify(new DocumentCompletedNotification($document));

        foreach ($document->signers as $signer) {
            if ($signer->email && ! $signer->is_owner) {
                Notification::route('mail', $signer->email)->notify(new DocumentCompletedNotification($document, $signer));
            }
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Sealing document PDF failed', ['document' => $this->document->id, 'error' => $exception->getMessage()]);

        $document = $this->document->fresh('user');

        if ($document === null || $document->status === DocumentStatus::Completed) {
            return;
        }

        $document->update([
            'processing_failed_at' => now(),
            'processing_error' => mb_substr($exception->getMessage(), 0, 2000),
        ]);
        $document->record(AuditEvent::ProcessingFailed, metadata: ['error' => mb_substr($exception->getMessage(), 0, 500)]);

        DocumentChanged::dispatch($document);
        $document->user->notify(new DocumentProcessingFailedNotification($document));
    }
}
