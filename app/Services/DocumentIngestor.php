<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Enums\DocumentStatus;
use App\Enums\SigningOrderMode;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DocumentIngestor
{
    public function __construct(private DocumentStorage $storage, private PdfEngine $engine) {}

    /**
     * @param  array{title: string, description?: ?string, expiry_days?: int}  $attributes
     *
     * @throws ValidationException
     */
    public function ingest(User $user, UploadedFile $file, array $attributes, ?string $thumbnailDataUrl = null): Document
    {
        $path = $file->getRealPath();

        if ($path === false || mime_content_type($path) !== 'application/pdf') {
            $this->reject('File harus berupa PDF.');
        }

        if ($file->getSize() > config('paraf.max_upload_kb') * 1024) {
            $this->reject('Ukuran PDF maksimal '.round(config('paraf.max_upload_kb') / 1024).' MB.');
        }

        $report = $this->engine->inspect($path);

        if (! $report['ok']) {
            $this->reject(match ($report['reason'] ?? 'invalid') {
                'encrypted' => 'PDF terproteksi password. Silakan unggah file tanpa password.',
                'unsafe' => 'PDF berisi script atau file tersemat ('.implode(', ', $report['features'] ?? []).') sehingga ditolak demi keamanan. Simpan ulang sebagai PDF biasa lalu unggah lagi.',
                default => 'File PDF rusak atau tidak bisa dibaca.',
            });
        }

        $contents = (string) file_get_contents($path);
        $documentId = (string) Str::uuid7();
        $directory = "documents/{$documentId}";
        $thumbnail = $this->decodeThumbnail($thumbnailDataUrl);

        $this->storage->put("{$directory}/original.pdf", $contents);

        if ($thumbnail !== null) {
            $this->storage->put("{$directory}/thumbnail.jpg", $thumbnail);
        }

        try {
            return DB::transaction(function () use ($user, $file, $attributes, $report, $contents, $documentId, $directory, $thumbnail) {
                $document = new Document([
                    'user_id' => $user->id,
                    'title' => $attributes['title'],
                    'description' => $attributes['description'] ?? null,
                    'status' => DocumentStatus::Draft,
                    'signing_order_mode' => SigningOrderMode::Parallel,
                    'original_filename' => $this->sanitizeFilename($file->getClientOriginalName()),
                    'original_pdf_path' => "{$directory}/original.pdf",
                    'thumbnail_path' => $thumbnail !== null ? "{$directory}/thumbnail.jpg" : null,
                    'original_hash_sha256' => hash('sha256', $contents),
                    'file_size' => strlen($contents),
                    'total_pages' => count($report['pages']),
                    'expiry_days' => $attributes['expiry_days'] ?? config('paraf.expiry_days.default'),
                ]);
                $document->id = $documentId;
                $document->save();

                $document->pages()->createMany(collect($report['pages'])->map(fn (array $page, int $index) => [
                    'page_number' => $index + 1,
                    'width_pt' => $page['width'],
                    'height_pt' => $page['height'],
                    'rotation_degrees' => $page['rotation'],
                ])->all());

                $document->record(AuditEvent::Created, metadata: [
                    'filename' => $document->original_filename,
                    'pages' => $document->total_pages,
                    'sha256' => $document->original_hash_sha256,
                ], userId: $user->id);

                return $document;
            });
        } catch (Throwable $e) {
            $this->storage->deleteDirectory($directory);

            throw $e;
        }
    }

    /**
     * The first-page preview is rendered in the browser with PDF.js, so the server does not
     * need Imagick/Ghostscript. Anything that is not a small JPEG/PNG is ignored.
     */
    private function decodeThumbnail(?string $dataUrl): ?string
    {
        if ($dataUrl === null || ! preg_match('#^data:image/(jpeg|png);base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $matches)) {
            return null;
        }

        $binary = base64_decode($matches[2], true);

        if ($binary === false || strlen($binary) > 1024 * 1024) {
            return null;
        }

        $info = @getimagesizefromstring($binary);

        if ($info === false || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true) || $info[0] > 1200 || $info[1] > 1600) {
            return null;
        }

        return $binary;
    }

    private function sanitizeFilename(string $name): string
    {
        $base = Str::of(pathinfo($name, PATHINFO_FILENAME))->ascii()->replaceMatches('/[^A-Za-z0-9 ._-]+/', '')->squish()->limit(120, '');

        return ($base->isEmpty() ? 'dokumen' : (string) $base).'.pdf';
    }

    /**
     * @throws ValidationException
     */
    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
