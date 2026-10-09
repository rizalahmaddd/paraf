<?php

namespace App\Http\Controllers;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\DocumentStorage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DocumentFileController extends Controller
{
    public function __construct(private DocumentStorage $storage) {}

    /**
     * Raw PDF for the PDF.js editor & live viewer.
     */
    public function preview(Document $document): Response
    {
        Gate::authorize('manage', $document);

        $variant = request()->query('variant');
        $useCompleted = ($variant === 'final' || ($variant === null && $document->status === DocumentStatus::Completed))
            && $document->completed_pdf_path
            && $this->storage->exists($document->completed_pdf_path);

        $path = $useCompleted ? $document->completed_pdf_path : $document->original_pdf_path;
        $filename = $useCompleted
            ? Str::slug($document->title).'-signed.pdf'
            : $document->original_filename;

        abort_unless($this->storage->exists($path), 404);

        return $this->pdfResponse($this->storage->get($path), 'inline', $filename);
    }

    public function thumbnail(Document $document): Response
    {
        Gate::authorize('manage', $document);

        abort_unless($this->storage->exists($document->thumbnail_path), 404);

        return response($this->storage->get($document->thumbnail_path), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }

    public function download(Document $document, string $variant): Response
    {
        $path = $variant === 'final' ? $document->completed_pdf_path : $document->original_pdf_path;

        abort_unless($this->storage->exists($path), 404);

        $suffix = $variant === 'final' ? '-signed' : '';

        return $this->pdfResponse($this->storage->get($path), 'attachment', Str::slug($document->title).$suffix.'.pdf');
    }

    public static function temporaryDownloadUrl(Document $document, string $variant = 'final'): string
    {
        return URL::temporarySignedRoute(
            'documents.download',
            now()->addMinutes(config('paraf.download_link_minutes')),
            ['document' => $document, 'variant' => $variant],
        );
    }

    private function pdfResponse(string $contents, string $disposition, string $filename): Response
    {
        return response($contents, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.addslashes($filename).'"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
