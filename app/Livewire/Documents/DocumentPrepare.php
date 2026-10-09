<?php

namespace App\Livewire\Documents;

use App\Enums\FieldType;
use App\Enums\SigningOrderMode;
use App\Models\Document;
use App\Models\DocumentField;
use App\Models\DocumentPage;
use App\Models\Signer;
use App\Services\DocumentTemplateService;
use App\Services\DocumentWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app', ['heading' => 'Siapkan Dokumen'])]
#[Title('Siapkan Dokumen')]
class DocumentPrepare extends Component
{
    #[Locked]
    public Document $document;

    #[Url(as: 'langkah')]
    public int $step = 1;

    public string $title = '';

    public string $description = '';

    public int $expiry_days = 14;

    public string $signing_order_mode = 'PARALLEL';

    public bool $ownerSigns = false;

    /**
     * @var list<array{id: ?string, name: string, email: string, phone: string, passcode: string, has_passcode: bool, is_owner: bool}>
     */
    public array $signers = [];

    public bool $sendViaEmail = true;

    public string $templateName = '';

    public function mount(Document $document): void
    {
        $this->authorize('manage', $document);

        if (! $document->isDraft()) {
            $this->redirectRoute('documents.show', $document, navigate: true);

            return;
        }

        $this->document = $document;
        $this->title = $document->title;
        $this->description = (string) $document->description;
        $this->expiry_days = $document->expiry_days;
        $this->signing_order_mode = $document->signing_order_mode->value;
        $this->sendViaEmail = $document->send_via_email;
        $this->loadSigners();

        if ($this->signers === []) {
            $this->addSigner();
        }

        $this->step = $this->allowedStep($this->step);
    }

    public function addSigner(): void
    {
        $this->signers[] = ['id' => null, 'name' => '', 'email' => '', 'phone' => '', 'passcode' => '', 'has_passcode' => false, 'is_owner' => false];
    }

    public function removeSigner(int $index): void
    {
        if (($this->signers[$index]['is_owner'] ?? false) === true) {
            $this->ownerSigns = false;
        }

        unset($this->signers[$index]);
        $this->signers = array_values($this->signers);
    }

    public function moveSigner(int $index, int $direction): void
    {
        $target = $index + $direction;

        if (! isset($this->signers[$index], $this->signers[$target])) {
            return;
        }

        [$this->signers[$index], $this->signers[$target]] = [$this->signers[$target], $this->signers[$index]];
    }

    public function clearPasscode(int $index): void
    {
        if (isset($this->signers[$index])) {
            $this->signers[$index]['passcode'] = '';
            $this->signers[$index]['has_passcode'] = false;
        }
    }

    public function updatedOwnerSigns(bool $value): void
    {
        $existing = collect($this->signers)->search(fn (array $signer) => $signer['is_owner']);

        if ($value && $existing === false) {
            $user = Auth::user();
            array_unshift($this->signers, [
                'id' => null,
                'name' => $user->name,
                'email' => (string) $user->email,
                'phone' => (string) $user->phone,
                'passcode' => '',
                'has_passcode' => false,
                'is_owner' => true,
            ]);
        }

        if (! $value && $existing !== false) {
            $this->removeSigner($existing);
        }
    }

    public function saveSigners(): void
    {
        $this->authorize('manage', $this->document);
        $this->ensureDraft();

        $this->validate([
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'expiry_days' => ['required', 'integer', 'min:'.config('paraf.expiry_days.min'), 'max:'.config('paraf.expiry_days.max')],
            'signing_order_mode' => ['required', Rule::enum(SigningOrderMode::class)],
            'signers' => ['required', 'array', 'min:1', 'max:20'],
            'signers.*.name' => ['required', 'string', 'max:150'],
            'signers.*.email' => ['nullable', 'email', 'max:150'],
            'signers.*.phone' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]{6,30}$/'],
            'signers.*.passcode' => ['nullable', 'digits:6'],
        ], [
            'signers.required' => 'Tambahkan minimal satu penandatangan.',
            'signers.*.name.required' => 'Nama penandatangan wajib diisi.',
            'signers.*.email.email' => 'Format email tidak valid.',
            'signers.*.phone.regex' => 'Nomor WhatsApp tidak valid.',
            'signers.*.passcode.digits' => 'Passcode harus 6 angka.',
        ]);

        $emails = collect($this->signers)->pluck('email')->filter()->map(fn (string $email) => Str::lower($email));
        if ($emails->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages(['signers' => 'Email penandatangan tidak boleh sama: '.$emails->duplicates()->first()]);
        }

        DB::transaction(function () {
            $this->document->update([
                'title' => $this->title,
                'description' => $this->description ?: null,
                'expiry_days' => $this->expiry_days,
                'signing_order_mode' => $this->signing_order_mode,
            ]);

            $keptIds = collect($this->signers)->pluck('id')->filter()->all();
            $this->document->signers()->whereNotIn('id', $keptIds)->delete();

            $colors = config('paraf.signer_colors');
            $colorIndex = 0;

            foreach (array_values($this->signers) as $index => $row) {
                $attributes = [
                    'name' => trim($row['name']),
                    'email' => $row['email'] !== '' ? Str::lower(trim($row['email'])) : null,
                    'phone' => $row['phone'] !== '' ? trim($row['phone']) : null,
                    'signing_order' => $index + 1,
                    'is_owner' => $row['is_owner'],
                    'color_tag' => $row['is_owner'] ? config('paraf.owner_color') : $colors[$colorIndex++ % count($colors)],
                ];

                if ($row['passcode'] !== '') {
                    $attributes['passcode_hash'] = Hash::make($row['passcode']);
                } elseif (! $row['has_passcode']) {
                    $attributes['passcode_hash'] = null;
                }

                $signer = $row['id'] ? $this->document->signers()->whereKey($row['id'])->first() : null;
                $signer ? $signer->update($attributes) : $this->document->signers()->create($attributes);
            }
        });

        $this->document->refresh();
        $this->loadSigners();
        $this->step = 2;
        $this->dispatch('notify', message: 'Penandatangan disimpan.', type: 'success');
    }

    /**
     * Called from the Alpine editor; replaces the whole layout so deletions are captured too.
     *
     * @param  list<array<string, mixed>>  $fields
     * @return array{ok: bool, message?: string}
     */
    public function saveFields(array $fields): array
    {
        $this->authorize('manage', $this->document);

        if (! $this->document->fresh()->isDraft()) {
            return ['ok' => false, 'message' => 'Dokumen sudah dikirim; tata letak terkunci.'];
        }

        $signerIds = $this->document->signers()->pluck('id')->all();

        $validator = Validator::make(['fields' => $fields], [
            'fields' => ['array', 'max:500'],
            'fields.*.signer_id' => ['required', Rule::in($signerIds)],
            'fields.*.page' => ['required', 'integer', 'min:1', 'max:'.$this->document->total_pages],
            'fields.*.x' => ['required', 'numeric', 'min:0', 'max:1'],
            'fields.*.y' => ['required', 'numeric', 'min:0', 'max:1'],
            'fields.*.w' => ['required', 'numeric', 'gt:0', 'max:1'],
            'fields.*.h' => ['required', 'numeric', 'gt:0', 'max:1'],
            'fields.*.type' => ['required', Rule::enum(FieldType::class)],
            'fields.*.label' => ['nullable', 'string', 'max:100'],
            'fields.*.required' => ['boolean'],
        ]);

        if ($validator->fails()) {
            return ['ok' => false, 'message' => 'Tata letak tidak valid: '.$validator->errors()->first()];
        }

        DB::transaction(function () use ($fields) {
            $this->document->fields()->delete();

            foreach ($fields as $field) {
                $type = FieldType::from($field['type']);

                $this->document->fields()->create([
                    'signer_id' => $field['signer_id'],
                    'page_number' => (int) $field['page'],
                    'x_ratio' => round(min((float) $field['x'], 1 - (float) $field['w']), 4),
                    'y_ratio' => round(min((float) $field['y'], 1 - (float) $field['h']), 4),
                    'width_ratio' => round((float) $field['w'], 4),
                    'height_ratio' => round((float) $field['h'], 4),
                    'field_type' => $type,
                    'label' => in_array($type, [FieldType::Text, FieldType::Checkbox], true) ? ($field['label'] ?? null) : null,
                    'is_required' => $type->isImage() || (bool) ($field['required'] ?? true),
                ]);
            }

            $this->document->touch();
        });

        return ['ok' => true];
    }

    public function updatedStep(): void
    {
        $this->step = $this->allowedStep($this->step);
    }

    public function goToStep(int $step): void
    {
        $allowed = $this->allowedStep($step);

        if ($allowed < $step) {
            $this->dispatch('notify', message: $allowed === 1 ? 'Simpan penandatangan terlebih dahulu.' : 'Setiap penandatangan perlu minimal satu kotak tanda tangan.', type: 'error');
        }

        $this->step = $allowed;
    }

    public function send(DocumentWorkflow $workflow): void
    {
        $this->authorize('manage', $this->document);

        $workflow->send($this->document, Auth::user(), $this->sendViaEmail);

        session()->flash('show_distribution', true);
        $this->redirectRoute('documents.show', $this->document, navigate: true);
    }

    public function deleteDraft(DocumentWorkflow $workflow): void
    {
        $this->authorize('manage', $this->document);
        $workflow->deleteDraft($this->document);

        $this->redirectRoute('documents.index', navigate: true);
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

    public function render(): View
    {
        $this->document->load(['signers.fields', 'pages']);

        return view('livewire.documents.document-prepare', [
            'editorConfig' => $this->step === 2 ? $this->editorConfig() : null,
            'missingSignature' => $this->document->signers->filter(fn (Signer $signer) => ! $signer->fields->contains(fn (DocumentField $field) => $field->field_type->isImage())),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function editorConfig(): array
    {
        return [
            'pdfUrl' => route('documents.pdf', $this->document),
            'pages' => $this->document->pages->map(fn (DocumentPage $page) => ['number' => $page->page_number] + $page->displaySize())->values(),
            'signers' => $this->document->signers->map(fn (Signer $signer) => [
                'id' => $signer->id,
                'name' => $signer->name,
                'color' => $signer->color_tag,
                'is_owner' => $signer->is_owner,
            ])->values(),
            'fields' => $this->document->fields()->get()->map(fn (DocumentField $field) => $field->toCanvasArray())->values(),
        ];
    }

    private function allowedStep(int $step): int
    {
        $step = max(1, min(3, $step));
        $signers = $this->document->signers()->with('fields')->get();

        if ($step >= 2 && $signers->isEmpty()) {
            return 1;
        }

        if ($step === 3 && $signers->contains(fn (Signer $signer) => ! $signer->fields->contains(fn (DocumentField $field) => $field->field_type->isImage()))) {
            return 2;
        }

        return $step;
    }

    private function loadSigners(): void
    {
        $this->signers = $this->document->signers()->get()->map(fn (Signer $signer) => [
            'id' => $signer->id,
            'name' => $signer->name,
            'email' => (string) $signer->email,
            'phone' => (string) $signer->phone,
            'passcode' => '',
            'has_passcode' => $signer->hasPasscode(),
            'is_owner' => $signer->is_owner,
        ])->all();

        $this->ownerSigns = collect($this->signers)->contains('is_owner', true);
    }

    private function ensureDraft(): void
    {
        if (! $this->document->fresh()->isDraft()) {
            throw ValidationException::withMessages(['signers' => 'Dokumen sudah dikirim; penandatangan tidak bisa diubah di sini.']);
        }
    }
}
