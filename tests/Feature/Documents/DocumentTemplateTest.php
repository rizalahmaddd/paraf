<?php

use App\Enums\DocumentStatus;
use App\Enums\FieldType;
use App\Enums\SigningOrderMode;
use App\Livewire\Documents\DocumentIndex;
use App\Livewire\Documents\DocumentPrepare;
use App\Models\Document;
use App\Models\User;
use App\Services\DocumentStorage;
use App\Services\DocumentTemplateService;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
});

function templateOwner(): User
{
    return actingAsRole('owner');
}

function makeTestDocument(User $user, bool $isTemplate = false): Document
{
    $pdf = samplePdf(1);
    $path = 'documents/sample-doc/original.pdf';
    app(DocumentStorage::class)->put($path, $pdf);

    $doc = Document::create([
        'user_id' => $user->id,
        'title' => 'Dokumen Sumber',
        'status' => DocumentStatus::Draft,
        'is_template' => $isTemplate,
        'signing_order_mode' => SigningOrderMode::Parallel,
        'send_via_email' => true,
        'original_filename' => 'sumber.pdf',
        'original_pdf_path' => $path,
        'file_size' => strlen($pdf),
        'total_pages' => 1,
        'expiry_days' => 14,
        'original_hash_sha256' => hash('sha256', $pdf),
    ]);

    $doc->pages()->create([
        'page_number' => 1,
        'width_pt' => 595.28,
        'height_pt' => 841.89,
        'rotation_degrees' => 0,
    ]);

    $signer = $doc->signers()->create([
        'name' => 'Budi Santoso',
        'email' => 'budi@example.com',
        'signing_order' => 1,
        'color_tag' => '#10b981',
        'is_owner' => false,
    ]);

    $doc->fields()->create([
        'signer_id' => $signer->id,
        'page_number' => 1,
        'x_ratio' => 0.2,
        'y_ratio' => 0.5,
        'width_ratio' => 0.25,
        'height_ratio' => 0.08,
        'field_type' => FieldType::Signature,
        'is_required' => true,
    ]);

    return $doc;
}

test('user can save document as template from DocumentTemplateService', function () {
    $user = templateOwner();
    $doc = makeTestDocument($user);

    $service = app(DocumentTemplateService::class);
    $template = $service->saveAsTemplate($doc, 'Template Kontrak Kerja', $user);

    expect($template->is_template)->toBeTrue()
        ->and($template->title)->toBe('Template Kontrak Kerja')
        ->and($template->pages)->toHaveCount(1)
        ->and($template->signers)->toHaveCount(1)
        ->and($template->fields)->toHaveCount(1)
        ->and($template->signers->first()->name)->toBe('Budi Santoso');

    // Creating document from template
    $newDoc = $service->createDocumentFromTemplate($template, 'Kontrak Budi Baru', $user);
    expect($newDoc->is_template)->toBeFalse()
        ->and($newDoc->title)->toBe('Kontrak Budi Baru')
        ->and($newDoc->pages)->toHaveCount(1)
        ->and($newDoc->signers)->toHaveCount(1)
        ->and($newDoc->fields)->toHaveCount(1)
        ->and($newDoc->fields->first()->signer_id)->toBe($newDoc->signers->first()->id);
});

test('user can save as template from DocumentPrepare component', function () {
    $user = templateOwner();
    $doc = makeTestDocument($user);

    Livewire::test(DocumentPrepare::class, ['document' => $doc])
        ->call('openSaveTemplate')
        ->assertDispatched('open-modal', 'save-template-modal')
        ->set('templateName', 'Template Dari Prepare')
        ->call('saveAsTemplate')
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    $template = Document::where('title', 'Template Dari Prepare')->first();
    expect($template)->not->toBeNull()
        ->and($template->is_template)->toBeTrue();
});

test('save template modal can be closed from DocumentPrepare', function () {
    $doc = makeTestDocument(templateOwner());

    Livewire::test(DocumentPrepare::class, ['document' => $doc])
        ->call('openSaveTemplate')
        ->set('templateName', '')
        ->call('saveAsTemplate')
        ->assertHasErrors('templateName')
        ->call('closeSaveTemplate')
        ->assertHasNoErrors()
        ->assertDispatched('close-modal', 'save-template-modal');
});

test('user can instantiate document from template via DocumentIndex', function () {
    $user = templateOwner();
    $template = makeTestDocument($user, isTemplate: true);
    $template->update(['title' => 'Template Standar SPK']);

    $component = Livewire::test(DocumentIndex::class)
        ->set('selectedTemplateId', $template->id)
        ->set('templateDocTitle', 'SPK Vendor ABC')
        ->call('createFromTemplate')
        ->assertHasNoErrors();

    $newDoc = Document::where('title', 'SPK Vendor ABC')->first();
    expect($newDoc)->not->toBeNull()
        ->and($newDoc->is_template)->toBeFalse();

    $component->assertRedirect(route('documents.prepare', $newDoc));
});

test('user can delete template from DocumentIndex', function () {
    $user = templateOwner();
    $template = makeTestDocument($user, isTemplate: true);

    Livewire::test(DocumentIndex::class)
        ->call('deleteTemplate', $template->id)
        ->assertHasNoErrors()
        ->assertDispatched('notify');

    expect(Document::find($template->id))->toBeNull();
});

test('cleanup of abandoned drafts keeps templates', function () {
    $user = templateOwner();
    $template = makeTestDocument($user, isTemplate: true);
    Document::query()->whereKey($template->id)->update(['updated_at' => now()->subDays(60)]);

    $this->artisan('paraf:cleanup-drafts')->assertSuccessful();

    expect(Document::find($template->id))->not->toBeNull();
});

test('a template cannot be sent to signers', function () {
    $user = templateOwner();
    $template = makeTestDocument($user, isTemplate: true);

    Livewire::test(DocumentPrepare::class, ['document' => $template])
        ->call('send')
        ->assertHasErrors('document');

    expect($template->fresh()->status)->toBe(DocumentStatus::Draft);
});
