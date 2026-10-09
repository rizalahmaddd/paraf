<?php

namespace App\Livewire\Documents;

use App\Enums\DocumentStatus;
use App\Http\Controllers\DocumentFileController;
use App\Models\Document;
use App\Models\DocumentField;
use App\Models\DocumentPage;
use App\Models\Signer;
use App\Services\DocumentTemplateService;
use App\Services\DocumentWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app', ['heading' => 'Detail Dokumen'])]
#[Title('Detail Dokumen')]
class DocumentShow extends Component
{
    #[Locked]
    public Document $document;

    public ?string $editingSignerId = null;

    public string $contactName = '';

    public string $contactEmail = '';

    public string $contactPhone = '';

    public string $voidReason = '';

    public string $deleteConfirmation = '';

    public bool $showDistribution = false;

    public string $templateName = '';

    public function mount(Document $document): void
    {
        $this->authorize('manage', $document);

        if ($document->isDraft()) {
            $this->redirectRoute('documents.prepare', $document, navigate: true);

            return;
        }

        $this->document = $document;
        $this->showDistribution = (bool) session('show_distribution');
    }

    public function remind(string $signerId, DocumentWorkflow $workflow): void
    {
        $this->authorize('manage', $this->document);
        $signer = $this->findSigner($signerId);

        $this->attempt(fn () => $workflow->remind($signer, Auth::user()), "Pengingat dikirim ke {$signer->email}.");
    }

    public function editContact(string $signerId): void
    {
        $signer = $this->findSigner($signerId);

        $this->editingSignerId = $signer->id;
        $this->contactName = $signer->name;
        $this->contactEmail = (string) $signer->email;
        $this->contactPhone = (string) $signer->phone;
        $this->resetValidation();
        $this->dispatch('open-modal', 'edit-contact');
    }

    public function closeContact(): void
    {
        $this->editingSignerId = null;
        $this->dispatch('close-modal', 'edit-contact');
    }

    public function saveContact(DocumentWorkflow $workflow): void
    {
        $this->authorize('manage', $this->document);
        $signer = $this->findSigner((string) $this->editingSignerId);

        $data = $this->validate([
            'contactName' => ['required', 'string', 'max:150'],
            'contactEmail' => ['nullable', 'email', 'max:150'],
            'contactPhone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
        ], [
            'contactName.required' => 'Nama wajib diisi.',
            'contactEmail.email' => 'Format email tidak valid.',
            'contactPhone.regex' => 'Nomor WhatsApp tidak valid.',
        ]);

        try {
            $workflow->updateContactAndResend($signer, [
                'name' => $data['contactName'],
                'email' => $data['contactEmail'] ?: null,
                'phone' => $data['contactPhone'] ?: null,
            ], Auth::user());
        } catch (ValidationException $e) {
            $this->addError('contactEmail', collect($e->errors())->flatten()->first());

            return;
        }

        $this->closeContact();
        $this->dispatch('notify', message: 'Kontak diperbarui. Tautan lama sudah tidak berlaku; bagikan tautan baru.', type: 'success');
    }

    public function void(DocumentWorkflow $workflow): void
    {
        $this->authorize('manage', $this->document);
        $this->validate(['voidReason' => ['nullable', 'string', 'max:500']]);

        $this->attempt(fn () => $workflow->void($this->document, Auth::user(), $this->voidReason ?: null), 'Dokumen dibatalkan. Semua tautan signer sudah nonaktif.');
        $this->dispatch('close-modal', 'void-document');
    }

    public function deleteDocument(DocumentWorkflow $workflow): void
    {
        $this->authorize('manage', $this->document);

        $this->validate(
            ['deleteConfirmation' => ['required', 'in:HAPUS']],
            ['deleteConfirmation.required' => 'Ketik HAPUS untuk melanjutkan.', 'deleteConfirmation.in' => 'Ketik HAPUS untuk melanjutkan.'],
        );

        try {
            $workflow->delete($this->document);
        } catch (ValidationException $e) {
            $this->dispatch('close-modal', 'delete-document');
            $this->dispatch('notify', message: collect($e->errors())->flatten()->first(), type: 'error');

            return;
        }

        session()->flash('notify', ['message' => 'Dokumen dihapus.', 'type' => 'success']);
        $this->redirectRoute('documents.index', navigate: true);
    }

    public function retrySealing(DocumentWorkflow $workflow): void
    {
        $this->authorize('manage', $this->document);

        $this->attempt(fn () => $workflow->retrySealing($this->document), 'PDF final sedang diproses ulang.');
    }

    public function download(string $variant = 'final'): void
    {
        $this->authorize('manage', $this->document);

        if ($variant === 'final' && $this->document->status !== DocumentStatus::Completed) {
            return;
        }

        $this->redirect(DocumentFileController::temporaryDownloadUrl($this->document, $variant === 'final' ? 'final' : 'original'));
    }

    public function openSaveTemplate(): void
    {
        $this->templateName = $this->document->title.' (Template)';
        $this->resetValidation();
        $this->dispatch('open-modal', 'save-template-modal');
    }

    public function closeSaveTemplate(): void
    {
        $this->resetValidation('templateName');
        $this->dispatch('close-modal', 'save-template-modal');
    }

    public function saveAsTemplate(DocumentTemplateService $templateService): void
    {
        $this->authorize('manage', $this->document);

        $this->validate([
            'templateName' => ['required', 'string', 'max:150'],
        ], [
            'templateName.required' => 'Nama template wajib diisi.',
        ]);

        $templateService->saveAsTemplate($this->document, $this->templateName, Auth::user());

        $this->dispatch('close-modal', 'save-template-modal');
        $this->dispatch('notify', message: 'Dokumen berhasil disimpan sebagai template.', type: 'success');
    }

    /**
     * @return array<int, string>
     */
    public function getListeners(): array
    {
        return ['echo-private:App.Models.User.'.Auth::id().',.document.changed' => '$refresh'];
    }

    public function render(): View
    {
        $this->document->refresh()->load(['signers.fields', 'auditLogs.signer', 'auditLogs.user', 'pages']);

        $signers = $this->document->signers;
        $activeIds = $this->document->status->isInProgress() ? $this->document->activeSigners()->pluck('id')->all() : [];

        $hasCompleted = $this->document->status === DocumentStatus::Completed && (bool) $this->document->completed_pdf_path;

        $pages = $this->document->pages->map(fn (DocumentPage $page) => [
            'number' => $page->page_number,
        ] + $page->displaySize())->values();

        $previewConfig = [
            'pdfUrl' => route('documents.pdf', $this->document),
            'originalPdfUrl' => route('documents.pdf', ['document' => $this->document, 'variant' => 'original']),
            'finalPdfUrl' => $hasCompleted ? route('documents.pdf', ['document' => $this->document, 'variant' => 'final']) : null,
            'hasCompleted' => $hasCompleted,
            'status' => $this->document->status->value,
            'pages' => $pages,
            'signers' => $signers->map(fn (Signer $s) => [
                'id' => $s->id,
                'name' => $s->name,
                'color' => $s->color_tag,
                'is_owner' => $s->is_owner,
                'status' => $s->status->value,
            ])->values(),
            'fields' => $this->document->fields()->get()->map(fn (DocumentField $f) => $f->toCanvasArray() + [
                'is_filled' => ! is_null($f->filled_at),
            ])->values(),
        ];

        return view('livewire.documents.document-show', [
            'signers' => $signers,
            'activeIds' => $activeIds,
            'ownerSigner' => $signers->first(fn (Signer $signer) => $signer->is_owner && in_array($signer->id, $activeIds, true)),
            'signedCount' => $signers->where('status.value', 'SIGNED')->count(),
            'previewConfig' => $previewConfig,
        ]);
    }

    private function findSigner(string $signerId): Signer
    {
        return $this->document->signers()->whereKey($signerId)->firstOrFail();
    }

    private function attempt(callable $action, string $success): void
    {
        try {
            $action();
            $this->dispatch('notify', message: $success, type: 'success');
        } catch (ValidationException $e) {
            $this->dispatch('notify', message: collect($e->errors())->flatten()->first(), type: 'error');
        }
    }
}
