<?php

namespace App\Livewire\Documents;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Services\DocumentIngestor;
use App\Services\DocumentTemplateService;
use App\Services\DocumentWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

#[Layout('layouts.app', ['heading' => 'Dokumen'])]
#[Title('Dokumen')]
class DocumentIndex extends Component
{
    use WithFileUploads, WithPagination;

    public const FILTERS = [
        'semua' => 'Semua',
        'draft' => 'Draft',
        'berjalan' => 'Berjalan',
        'selesai' => 'Selesai',
        'perhatian' => 'Perlu perhatian',
        'template' => 'Template',
    ];

    #[Url(as: 'cari', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: 'semua')]
    public string $filter = 'semua';

    #[Url(as: 'tampilan', except: 'tile')]
    public string $viewMode = 'tile';

    public ?string $selectedTemplateId = null;

    public string $templateDocTitle = '';

    public ?string $confirmingDocumentId = null;

    public string $deleteConfirmation = '';

    /** @var TemporaryUploadedFile|null */
    public $file = null;

    public string $title = '';

    public string $description = '';

    public int $expiry_days = 14;

    public ?string $thumbnail = null;

    public function mount(): void
    {
        $this->expiry_days = config('paraf.expiry_days.default');
        $this->viewMode = request('tampilan') ?: session('document_view_mode', 'tile');

        if (request()->boolean('unggah')) {
            $this->dispatch('open-modal', 'upload-document');
        }
    }

    public function setViewMode(string $mode): void
    {
        if (in_array($mode, ['tile', 'list'], true)) {
            $this->viewMode = $mode;
            session(['document_view_mode' => $mode]);
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function setFilter(string $filter): void
    {
        $this->filter = array_key_exists($filter, self::FILTERS) ? $filter : 'semua';
        $this->resetPage();
    }

    public function openUpload(): void
    {
        $this->reset(['file', 'title', 'description', 'thumbnail']);
        $this->expiry_days = config('paraf.expiry_days.default');
        $this->resetValidation();
        $this->dispatch('open-modal', 'upload-document');
    }

    public function closeUpload(): void
    {
        $this->reset(['file', 'title', 'description', 'thumbnail']);
        $this->resetValidation();
        $this->dispatch('close-modal', 'upload-document');
    }

    public function uploadDocument(DocumentIngestor $ingestor): void
    {
        $this->authorize('manage-documents');

        $validated = $this->validate([
            'file' => ['required', 'file', 'mimetypes:application/pdf', 'max:'.config('paraf.max_upload_kb')],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'expiry_days' => ['required', 'integer', 'min:'.config('paraf.expiry_days.min'), 'max:'.config('paraf.expiry_days.max')],
        ], [
            'file.required' => 'Pilih file PDF yang akan ditandatangani.',
            'file.mimetypes' => 'File harus berupa PDF.',
            'file.max' => 'Ukuran PDF maksimal '.round(config('paraf.max_upload_kb') / 1024).' MB.',
        ]);

        $document = $ingestor->ingest(Auth::user(), $this->file, [
            'title' => $validated['title'],
            'description' => $validated['description'] ?: null,
            'expiry_days' => $validated['expiry_days'],
        ], $this->thumbnail);

        $this->redirectRoute('documents.prepare', $document, navigate: true);
    }

    public function openUseTemplate(?string $templateId = null): void
    {
        $this->selectedTemplateId = $templateId;
        if ($templateId) {
            $tpl = Document::query()->ownedBy(Auth::user())->templates()->find($templateId);
            $this->templateDocTitle = $tpl ? $tpl->title.' (Baru)' : '';
        } else {
            $this->templateDocTitle = '';
        }
        $this->resetValidation();
        $this->dispatch('open-modal', 'use-template-modal');
    }

    public function closeUseTemplate(): void
    {
        $this->reset(['selectedTemplateId', 'templateDocTitle']);
        $this->resetValidation();
        $this->dispatch('close-modal', 'use-template-modal');
    }

    public function createFromTemplate(DocumentTemplateService $templateService): void
    {
        $this->authorize('manage-documents');

        $this->validate([
            'selectedTemplateId' => ['required', 'exists:documents,id'],
            'templateDocTitle' => ['required', 'string', 'max:150'],
        ], [
            'selectedTemplateId.required' => 'Pilih template terlebih dahulu.',
            'templateDocTitle.required' => 'Judul dokumen wajib diisi.',
        ]);

        $template = Document::query()
            ->ownedBy(Auth::user())
            ->templates()
            ->findOrFail($this->selectedTemplateId);

        $document = $templateService->createDocumentFromTemplate($template, $this->templateDocTitle, Auth::user());

        $this->closeUseTemplate();
        $this->redirectRoute('documents.prepare', $document, navigate: true);
    }

    public function deleteTemplate(string $templateId, DocumentTemplateService $templateService): void
    {
        $this->authorize('manage-documents');

        $template = Document::query()
            ->ownedBy(Auth::user())
            ->templates()
            ->findOrFail($templateId);

        $templateService->deleteTemplate($template);

        $this->dispatch('notify', message: 'Template berhasil dihapus.', type: 'success');
    }

    public function confirmDeleteDocument(string $documentId): void
    {
        $this->confirmingDocumentId = $documentId;
        $this->deleteConfirmation = '';
        $this->resetValidation('deleteConfirmation');
        $this->dispatch('open-modal', 'delete-document');
    }

    public function deleteDocument(DocumentWorkflow $workflow): void
    {
        $this->authorize('manage-documents');

        $this->validate(
            ['deleteConfirmation' => ['required', 'in:HAPUS']],
            ['deleteConfirmation.required' => 'Ketik HAPUS untuk melanjutkan.', 'deleteConfirmation.in' => 'Ketik HAPUS untuk melanjutkan.'],
        );

        $document = Document::query()
            ->ownedBy(Auth::user())
            ->notTemplates()
            ->findOrFail($this->confirmingDocumentId);

        $this->reset(['confirmingDocumentId', 'deleteConfirmation']);
        $this->dispatch('close-modal', 'delete-document');

        try {
            $workflow->delete($document);
        } catch (ValidationException $e) {
            $this->dispatch('notify', message: collect($e->errors())->flatten()->first(), type: 'error');

            return;
        }

        $this->dispatch('notify', message: 'Dokumen dihapus.', type: 'success');
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        $statuses = Document::query()->ownedBy(Auth::user())
            ->notTemplates()
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        $templatesCount = Document::query()->ownedBy(Auth::user())
            ->templates()
            ->count();

        $sum = fn (array $cases) => (int) collect($cases)->sum(fn (DocumentStatus $status) => $statuses[$status->value] ?? 0);

        return [
            'semua' => (int) $statuses->sum(),
            'draft' => $sum([DocumentStatus::Draft]),
            'berjalan' => $sum(DocumentStatus::inProgress()),
            'selesai' => $sum([DocumentStatus::Completed]),
            'perhatian' => $sum([DocumentStatus::Declined, DocumentStatus::Expired]),
            'template' => $templatesCount,
        ];
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
        $isTemplateFilter = $this->filter === 'template';

        $documents = Document::query()
            ->ownedBy(Auth::user())
            ->when($isTemplateFilter, fn (Builder $query) => $query->templates())
            ->when(! $isTemplateFilter, fn (Builder $query) => $query->notTemplates())
            ->with([
                'signers' => fn ($query) => $query->select('id', 'document_id', 'name', 'color_tag', 'signing_order', 'status'),
            ])
            ->withCount([
                'signers',
                'signers as signed_count' => fn (Builder $query) => $query->where('status', 'SIGNED'),
                'fields',
            ])
            ->when($this->search !== '', fn (Builder $query) => $query->where('title', 'like', "%{$this->search}%"))
            ->when(! $isTemplateFilter && $this->filter === 'draft', fn (Builder $query) => $query->where('status', DocumentStatus::Draft))
            ->when(! $isTemplateFilter && $this->filter === 'berjalan', fn (Builder $query) => $query->whereIn('status', DocumentStatus::inProgress()))
            ->when(! $isTemplateFilter && $this->filter === 'selesai', fn (Builder $query) => $query->where('status', DocumentStatus::Completed))
            ->when(! $isTemplateFilter && $this->filter === 'perhatian', fn (Builder $query) => $query->whereIn('status', [DocumentStatus::Declined, DocumentStatus::Expired]))
            ->latest('updated_at')
            ->paginate(12);

        $availableTemplates = Document::query()
            ->ownedBy(Auth::user())
            ->templates()
            ->select('id', 'title', 'total_pages', 'created_at')
            ->latest('updated_at')
            ->get();

        return view('livewire.documents.document-index', [
            'documents' => $documents,
            'counts' => $this->counts(),
            'availableTemplates' => $availableTemplates,
        ]);
    }
}
