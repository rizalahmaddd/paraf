<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Enums\DocumentStatus;
use App\Enums\SignerStatus;
use App\Enums\SigningOrderMode;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DocumentTemplateService
{
    public function __construct(private DocumentStorage $storage) {}

    /**
     * Save an existing document as a reusable template.
     */
    public function saveAsTemplate(Document $sourceDocument, string $templateTitle, User $user): Document
    {
        $sourceDocument->loadMissing(['pages', 'signers.fields', 'fields']);

        $templateId = (string) Str::uuid7();
        $targetDir = "documents/{$templateId}";

        if (! $this->storage->exists($sourceDocument->original_pdf_path)) {
            throw ValidationException::withMessages(['template' => 'File PDF sumber tidak ditemukan di storage.']);
        }

        $this->storage->disk()->copy($sourceDocument->original_pdf_path, "{$targetDir}/original.pdf");

        $thumbnailPath = null;
        if ($sourceDocument->thumbnail_path && $this->storage->exists($sourceDocument->thumbnail_path)) {
            $thumbnailPath = "{$targetDir}/thumbnail.jpg";
            $this->storage->disk()->copy($sourceDocument->thumbnail_path, $thumbnailPath);
        }

        return DB::transaction(function () use ($sourceDocument, $templateTitle, $user, $templateId, $targetDir, $thumbnailPath) {
            $template = new Document([
                'user_id' => $user->id,
                'title' => $templateTitle,
                'description' => $sourceDocument->description,
                'status' => DocumentStatus::Draft,
                'signing_order_mode' => $sourceDocument->signing_order_mode ?? SigningOrderMode::Parallel,
                'send_via_email' => $sourceDocument->send_via_email ?? true,
                'original_filename' => $sourceDocument->original_filename,
                'original_pdf_path' => "{$targetDir}/original.pdf",
                'thumbnail_path' => $thumbnailPath,
                'original_hash_sha256' => $sourceDocument->original_hash_sha256,
                'file_size' => $sourceDocument->file_size,
                'total_pages' => $sourceDocument->total_pages,
                'expiry_days' => $sourceDocument->expiry_days,
            ]);
            $template->id = $templateId;
            $template->is_template = true;
            $template->save();

            foreach ($sourceDocument->pages as $page) {
                $template->pages()->create([
                    'page_number' => $page->page_number,
                    'width_pt' => $page->width_pt,
                    'height_pt' => $page->height_pt,
                    'rotation_degrees' => $page->rotation_degrees,
                    'thumbnail_path' => $page->thumbnail_path,
                ]);
            }

            $signerIdMap = [];
            foreach ($sourceDocument->signers as $signer) {
                $newSigner = $template->signers()->create([
                    'name' => $signer->name,
                    'email' => $signer->email,
                    'phone' => $signer->phone,
                    'color_tag' => $signer->color_tag,
                    'signing_order' => $signer->signing_order,
                    'is_owner' => $signer->is_owner,
                    'status' => SignerStatus::Pending,
                ]);
                $signerIdMap[$signer->id] = $newSigner->id;
            }

            foreach ($sourceDocument->fields as $field) {
                $newSignerId = $signerIdMap[$field->signer_id] ?? null;
                if ($newSignerId) {
                    $template->fields()->create([
                        'signer_id' => $newSignerId,
                        'page_number' => $field->page_number,
                        'x_ratio' => $field->x_ratio,
                        'y_ratio' => $field->y_ratio,
                        'width_ratio' => $field->width_ratio,
                        'height_ratio' => $field->height_ratio,
                        'field_type' => $field->field_type,
                        'label' => $field->label,
                        'is_required' => $field->is_required,
                    ]);
                }
            }

            $template->record(AuditEvent::Created, metadata: [
                'action' => 'saved_as_template',
                'source_document_id' => $sourceDocument->id,
            ], userId: $user->id);

            return $template;
        });
    }

    /**
     * Create a new document draft from an existing template.
     */
    public function createDocumentFromTemplate(Document $template, string $documentTitle, User $user): Document
    {
        if (! $template->is_template) {
            throw ValidationException::withMessages(['template' => 'Dokumen yang dipilih bukan template.']);
        }

        $template->loadMissing(['pages', 'signers.fields', 'fields']);

        $documentId = (string) Str::uuid7();
        $targetDir = "documents/{$documentId}";

        if (! $this->storage->exists($template->original_pdf_path)) {
            throw ValidationException::withMessages(['template' => 'File PDF sumber tidak ditemukan di storage.']);
        }

        $this->storage->disk()->copy($template->original_pdf_path, "{$targetDir}/original.pdf");

        $thumbnailPath = null;
        if ($template->thumbnail_path && $this->storage->exists($template->thumbnail_path)) {
            $thumbnailPath = "{$targetDir}/thumbnail.jpg";
            $this->storage->disk()->copy($template->thumbnail_path, $thumbnailPath);
        }

        return DB::transaction(function () use ($template, $documentTitle, $user, $documentId, $targetDir, $thumbnailPath) {
            $document = new Document([
                'user_id' => $user->id,
                'title' => $documentTitle,
                'description' => $template->description,
                'status' => DocumentStatus::Draft,
                'signing_order_mode' => $template->signing_order_mode ?? SigningOrderMode::Parallel,
                'send_via_email' => $template->send_via_email ?? true,
                'original_filename' => $template->original_filename,
                'original_pdf_path' => "{$targetDir}/original.pdf",
                'thumbnail_path' => $thumbnailPath,
                'original_hash_sha256' => $template->original_hash_sha256,
                'file_size' => $template->file_size,
                'total_pages' => $template->total_pages,
                'expiry_days' => $template->expiry_days,
            ]);
            $document->id = $documentId;
            $document->is_template = false;
            $document->save();

            foreach ($template->pages as $page) {
                $document->pages()->create([
                    'page_number' => $page->page_number,
                    'width_pt' => $page->width_pt,
                    'height_pt' => $page->height_pt,
                    'rotation_degrees' => $page->rotation_degrees,
                    'thumbnail_path' => $page->thumbnail_path,
                ]);
            }

            $signerIdMap = [];
            foreach ($template->signers as $signer) {
                $newSigner = $document->signers()->create([
                    'name' => $signer->name,
                    'email' => $signer->email,
                    'phone' => $signer->phone,
                    'color_tag' => $signer->color_tag,
                    'signing_order' => $signer->signing_order,
                    'is_owner' => $signer->is_owner,
                    'status' => SignerStatus::Pending,
                ]);
                $signerIdMap[$signer->id] = $newSigner->id;
            }

            foreach ($template->fields as $field) {
                $newSignerId = $signerIdMap[$field->signer_id] ?? null;
                if ($newSignerId) {
                    $document->fields()->create([
                        'signer_id' => $newSignerId,
                        'page_number' => $field->page_number,
                        'x_ratio' => $field->x_ratio,
                        'y_ratio' => $field->y_ratio,
                        'width_ratio' => $field->width_ratio,
                        'height_ratio' => $field->height_ratio,
                        'field_type' => $field->field_type,
                        'label' => $field->label,
                        'is_required' => $field->is_required,
                    ]);
                }
            }

            $document->record(AuditEvent::Created, metadata: [
                'action' => 'created_from_template',
                'template_id' => $template->id,
            ], userId: $user->id);

            return $document;
        });
    }

    public function deleteTemplate(Document $template): void
    {
        if (! $template->is_template) {
            throw ValidationException::withMessages(['template' => 'Dokumen bukan template.']);
        }

        $directory = "documents/{$template->id}";
        $this->storage->deleteDirectory($directory);
        $template->delete();
    }
}
