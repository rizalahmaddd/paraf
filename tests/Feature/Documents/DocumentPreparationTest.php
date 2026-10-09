<?php

use App\Enums\AuditEvent;
use App\Enums\DocumentStatus;
use App\Enums\FieldType;
use App\Livewire\Documents\DocumentIndex;
use App\Livewire\Documents\DocumentPrepare;
use App\Models\Document;
use App\Models\DocumentField;
use App\Models\Signer;
use App\Models\User;
use App\Notifications\SignatureRequestNotification;
use App\Services\DocumentStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
});

function owner(): User
{
    return actingAsRole('owner');
}

test('owner uploads a pdf and lands on the preparation page', function () {
    $user = owner();
    $file = UploadedFile::fake()->createWithContent('kontrak_kerja.pdf', samplePdf(2));

    $component = Livewire::test(DocumentIndex::class)
        ->set('file', $file)
        ->set('title', 'Kontrak Kerja')
        ->set('expiry_days', 7)
        ->call('uploadDocument')
        ->assertHasNoErrors();

    $document = Document::sole();
    $component->assertRedirect(route('documents.prepare', $document));

    expect($document->user_id)->toBe($user->id)
        ->and($document->status)->toBe(DocumentStatus::Draft)
        ->and($document->total_pages)->toBe(2)
        ->and($document->pages)->toHaveCount(2)
        ->and($document->original_filename)->toBe('kontrak_kerja.pdf')
        ->and($document->auditLogs->first()->event_type)->toBe(AuditEvent::Created);

    $stored = Storage::disk('local')->get($document->original_pdf_path);
    expect($stored)->not->toStartWith('%PDF')
        ->and(hash('sha256', app(DocumentStorage::class)->get($document->original_pdf_path)))->toBe($document->original_hash_sha256);
});

test('password protected pdfs are rejected with a clear message', function () {
    owner();

    Livewire::test(DocumentIndex::class)
        ->set('file', UploadedFile::fake()->createWithContent('rahasia.pdf', samplePdf(1, 'secret')))
        ->set('title', 'Rahasia')
        ->call('uploadDocument')
        ->assertHasErrors(['file' => 'PDF terproteksi password. Silakan unggah file tanpa password.']);

    expect(Document::count())->toBe(0);
});

test('non pdf uploads are rejected', function () {
    owner();

    Livewire::test(DocumentIndex::class)
        ->set('file', UploadedFile::fake()->create('foto.png', 10, 'image/png'))
        ->set('title', 'Foto')
        ->call('uploadDocument')
        ->assertHasErrors('file');
});

test('documents of other owners are not accessible', function () {
    $document = Document::factory()->create();
    owner();

    $this->get(route('documents.prepare', $document))->assertForbidden();
    $this->get(route('documents.show', $document))->assertForbidden();
    $this->get(route('documents.pdf', $document))->assertForbidden();
});

test('owner adds signers including themselves in sequential order', function () {
    $user = owner();
    $document = Document::factory()->for($user)->create(['total_pages' => 1]);

    Livewire::test(DocumentPrepare::class, ['document' => $document])
        ->set('ownerSigns', true)
        ->set('signing_order_mode', 'SEQUENTIAL')
        ->set('signers.1.name', 'Siti Aminah')
        ->set('signers.1.email', 'siti@example.test')
        ->set('signers.1.passcode', '123456')
        ->call('saveSigners')
        ->assertHasNoErrors()
        ->assertSet('step', 2);

    $signers = $document->fresh()->signers;
    expect($signers)->toHaveCount(2)
        ->and($signers[0]->is_owner)->toBeTrue()
        ->and($signers[0]->color_tag)->toBe(config('paraf.owner_color'))
        ->and($signers[1]->signing_order)->toBe(2)
        ->and($signers[1]->hasPasscode())->toBeTrue()
        ->and($document->fresh()->isSequential())->toBeTrue();
});

test('field layout only accepts signers of the document and stays within the page', function () {
    $user = owner();
    $document = Document::factory()->for($user)->create(['total_pages' => 2]);
    $signer = $document->signers()->create(['name' => 'Budi', 'email' => 'budi@example.test', 'color_tag' => '#2563EB']);
    $foreign = Signer::factory()->create();

    $component = Livewire::test(DocumentPrepare::class, ['document' => $document]);

    $result = $component->call('saveFields', [
        ['signer_id' => $foreign->id, 'page' => 1, 'x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.05, 'type' => 'SIGNATURE'],
    ])->effects['returns'][0];
    expect($result['ok'])->toBeFalse();

    $result = $component->call('saveFields', [
        ['signer_id' => $signer->id, 'page' => 3, 'x' => 0.1, 'y' => 0.1, 'w' => 0.2, 'h' => 0.05, 'type' => 'SIGNATURE'],
    ])->effects['returns'][0];
    expect($result['ok'])->toBeFalse();

    $result = $component->call('saveFields', [
        ['signer_id' => $signer->id, 'page' => 2, 'x' => 0.9, 'y' => 0.1, 'w' => 0.2, 'h' => 0.05, 'type' => 'SIGNATURE', 'required' => false],
        ['signer_id' => $signer->id, 'page' => 1, 'x' => 0.1, 'y' => 0.5, 'w' => 0.3, 'h' => 0.04, 'type' => 'TEXT', 'label' => 'NIK', 'required' => true],
    ])->effects['returns'][0];
    expect($result['ok'])->toBeTrue();

    $fields = $document->fields()->orderBy('page_number', 'desc')->get();
    expect($fields)->toHaveCount(2)
        ->and($fields[0]->x_ratio)->toBe(0.8)
        ->and($fields[0]->is_required)->toBeTrue()
        ->and($fields[1]->label)->toBe('NIK');
});

test('sending requires a signature box for every signer', function () {
    $user = owner();
    $document = Document::factory()->for($user)->create();
    $document->signers()->create(['name' => 'Budi', 'email' => 'budi@example.test', 'color_tag' => '#2563EB']);

    Livewire::test(DocumentPrepare::class, ['document' => $document])
        ->set('step', 3)
        ->assertSet('step', 2)
        ->call('send')
        ->assertHasErrors('document');

    expect($document->fresh()->status)->toBe(DocumentStatus::Draft);
});

test('sending issues links and emails the active signers', function () {
    Notification::fake();
    $user = owner();
    $document = Document::factory()->for($user)->sequential()->create(['expiry_days' => 10]);
    foreach (['Budi', 'Siti'] as $index => $name) {
        $signer = $document->signers()->create(['name' => $name, 'email' => strtolower($name).'@example.test', 'color_tag' => '#2563EB', 'signing_order' => $index + 1]);
        DocumentField::factory()->type(FieldType::Signature)->create(['document_id' => $document->id, 'signer_id' => $signer->id]);
    }

    Livewire::test(DocumentPrepare::class, ['document' => $document])
        ->set('sendViaEmail', true)
        ->call('send')
        ->assertRedirect(route('documents.show', $document));

    $document->refresh();
    expect($document->status)->toBe(DocumentStatus::WaitingForSignatures)
        ->and($document->expires_at->isSameDay(now()->addDays(10)))->toBeTrue()
        ->and($document->signers->every(fn ($signer) => $signer->signingUrl() !== null))->toBeTrue();

    Notification::assertSentOnDemand(SignatureRequestNotification::class, fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'budi@example.test');
    Notification::assertSentOnDemandTimes(SignatureRequestNotification::class, 1);
});

test('manual distribution sends no email but still activates links', function () {
    Notification::fake();
    $user = owner();
    $document = Document::factory()->for($user)->create();
    $signer = $document->signers()->create(['name' => 'Budi', 'email' => null, 'phone' => '081234567890', 'color_tag' => '#2563EB']);
    DocumentField::factory()->create(['document_id' => $document->id, 'signer_id' => $signer->id]);

    Livewire::test(DocumentPrepare::class, ['document' => $document])
        ->set('sendViaEmail', false)
        ->call('send')
        ->assertHasNoErrors();

    Notification::assertNothingSent();
    expect($signer->fresh()->whatsappUrl())->toStartWith('https://wa.me/6281234567890?text=')
        ->and($signer->fresh()->whatsappMessage())->toContain("tanda tangani dokumen '{$document->title}'");
});

test('email distribution requires an email for each signer', function () {
    $user = owner();
    $document = Document::factory()->for($user)->create();
    $signer = $document->signers()->create(['name' => 'Budi', 'email' => null, 'color_tag' => '#2563EB']);
    DocumentField::factory()->create(['document_id' => $document->id, 'signer_id' => $signer->id]);

    Livewire::test(DocumentPrepare::class, ['document' => $document])
        ->set('sendViaEmail', true)
        ->call('send')
        ->assertHasErrors('document');
});
