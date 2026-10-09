<?php

use App\Enums\AuditEvent;
use App\Enums\DocumentStatus;
use App\Enums\FieldType;
use App\Enums\SignerStatus;
use App\Http\Controllers\DocumentFileController;
use App\Jobs\ProcessCompletedDocumentPdf;
use App\Livewire\Documents\DocumentShow;
use App\Models\Document;
use App\Notifications\DocumentCompletedNotification;
use App\Notifications\DocumentProcessingFailedNotification;
use App\Notifications\SignatureRequestNotification;
use App\Services\DocumentStorage;
use App\Services\PdfEngine;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
});

function signEverything(Document $document): void
{
    $storage = app(DocumentStorage::class);

    foreach ($document->signers as $signer) {
        foreach ($signer->fields as $field) {
            $value = $field->field_type->isImage()
                ? $storage->put("documents/{$document->id}/signatures/{$field->id}.png", base64_decode(substr(samplePngDataUrl(), 22)))
                : ($field->field_type === FieldType::Checkbox ? '1' : 'Isi '.$field->field_type->value);
            $field->update(['field_value' => $value, 'filled_at' => now()]);
        }
        $signer->update(['status' => SignerStatus::Signed, 'viewed_at' => now()->subMinutes(5), 'signed_at' => now(), 'signed_ip_address' => '203.0.113.7', 'signed_user_agent' => 'Mozilla/5.0 Test']);
    }
}

test('the sealing job bakes signatures, appends the certificate and notifies everyone', function () {
    [$document] = sentDocument([['name' => 'Budi', 'fields' => [FieldType::Signature, FieldType::Initial, FieldType::Text, FieldType::Date]], ['name' => 'Siti']]);
    signEverything($document->fresh('signers.fields'));

    ProcessCompletedDocumentPdf::dispatchSync($document);

    $document->refresh();
    $storage = app(DocumentStorage::class);
    $final = $storage->get($document->completed_pdf_path);

    expect($document->status)->toBe(DocumentStatus::Completed)
        ->and($document->completed_at)->not->toBeNull()
        ->and($document->completed_hash_sha256)->toBe(hash('sha256', $final))
        ->and($document->signed_hash_sha256)->not->toBe($document->original_hash_sha256)
        ->and($document->auditLogs()->where('event_type', AuditEvent::Completed)->exists())->toBeTrue();

    $path = tempnam(sys_get_temp_dir(), 'final').'.pdf';
    File::put($path, $final);
    expect(count(app(PdfEngine::class)->inspect($path)['pages']))->toBeGreaterThan($document->total_pages);

    Notification::assertSentTo($document->user, DocumentCompletedNotification::class);
    Notification::assertSentOnDemandTimes(DocumentCompletedNotification::class, 2);
});

test('the sealing job is idempotent once the document is completed', function () {
    [$document] = sentDocument();
    signEverything($document->fresh('signers.fields'));
    ProcessCompletedDocumentPdf::dispatchSync($document);
    $hash = $document->fresh()->completed_hash_sha256;

    ProcessCompletedDocumentPdf::dispatchSync($document->fresh());

    expect($document->fresh()->completed_hash_sha256)->toBe($hash);
});

test('a failed sealing job is reported to the owner and can be retried', function () {
    [$document] = sentDocument();
    signEverything($document->fresh('signers.fields'));

    (new ProcessCompletedDocumentPdf($document))->failed(new RuntimeException('Disk penuh'));

    $document->refresh();
    expect($document->processing_failed_at)->not->toBeNull()
        ->and($document->status)->toBe(DocumentStatus::WaitingForSignatures);
    Notification::assertSentTo($document->user, DocumentProcessingFailedNotification::class);

    $this->actingAs($document->user);
    Livewire::test(DocumentShow::class, ['document' => $document])->call('retrySealing');

    expect($document->fresh()->status)->toBe(DocumentStatus::Completed);
});

test('the public verification page shows the seal without exposing contact details', function () {
    [$document] = sentDocument([['name' => 'Budi', 'email' => 'budi.santoso@example.test']]);
    signEverything($document->fresh('signers.fields'));
    ProcessCompletedDocumentPdf::dispatchSync($document);
    $document->refresh();

    $this->get(route('verify.show', $document))
        ->assertOk()
        ->assertSee($document->completed_hash_sha256)
        ->assertSee('Budi')
        ->assertDontSee('budi.santoso@example.test');

    $this->get(route('verify.show', Str::uuid()))->assertNotFound()->assertSee('Dokumen tidak terdaftar');
});

test('final downloads need a valid temporary signature', function () {
    [$document, $tokens] = sentDocument();
    signEverything($document->fresh('signers.fields'));
    ProcessCompletedDocumentPdf::dispatchSync($document);
    $document->refresh();

    $this->get(route('documents.download', ['document' => $document, 'variant' => 'final']))->assertForbidden();

    $url = DocumentFileController::temporaryDownloadUrl($document);
    $this->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');

    $this->get(route('sign.download', $tokens['Budi']))->assertRedirectContains('/unduh/final');

    $this->travel(16)->minutes();
    $this->get($url)->assertForbidden();
});

test('the expire command closes overdue documents but never fully signed ones', function () {
    [$overdue] = sentDocument([['name' => 'Budi']]);
    $overdue->update(['expires_at' => now()->subHour()]);

    [$waitingForSeal] = sentDocument([['name' => 'Siti']]);
    $waitingForSeal->update(['expires_at' => now()->subHour()]);
    $waitingForSeal->signers()->update(['status' => SignerStatus::Signed->value]);

    $this->artisan('paraf:expire-documents')->assertSuccessful();

    expect($overdue->fresh()->status)->toBe(DocumentStatus::Expired)
        ->and($overdue->auditLogs()->where('event_type', AuditEvent::Expired)->exists())->toBeTrue()
        ->and($waitingForSeal->fresh()->status)->toBe(DocumentStatus::WaitingForSignatures);
});

test('reminders go out three days and one day before expiry, once per day', function () {
    $this->travelTo(now()->setTime(9, 0));
    [$threeDays] = sentDocument([['name' => 'Budi']]);
    $threeDays->update(['expires_at' => now()->addDays(3)->setTime(15, 0)]);
    [$fiveDays] = sentDocument([['name' => 'Siti']]);
    $fiveDays->update(['expires_at' => now()->addDays(5)]);

    $this->artisan('paraf:send-reminders')->assertSuccessful();
    $this->artisan('paraf:send-reminders')->assertSuccessful();

    Notification::assertSentOnDemandTimes(SignatureRequestNotification::class, 1);
    expect($threeDays->auditLogs()->where('event_type', AuditEvent::ReminderSent)->count())->toBe(1);
});

test('abandoned drafts older than 30 days are removed with their files', function () {
    $old = Document::factory()->create(['original_pdf_path' => 'documents/x/original.pdf']);
    Storage::disk('local')->put("documents/{$old->id}/original.pdf", 'x');
    Document::query()->whereKey($old->id)->update(['updated_at' => now()->subDays(31)]);
    $recent = Document::factory()->create();

    $this->artisan('paraf:cleanup-drafts')->assertSuccessful();

    expect(Document::find($old->id))->toBeNull()
        ->and(Document::find($recent->id))->not->toBeNull()
        ->and(Storage::disk('local')->exists("documents/{$old->id}/original.pdf"))->toBeFalse();
});

test('owner can remind, void and see the audit trail from the detail page', function () {
    [$document] = sentDocument([['name' => 'Budi'], ['name' => 'Siti']]);
    $this->actingAs($document->user);

    Livewire::test(DocumentShow::class, ['document' => $document])
        ->assertSee('Budi')
        ->assertSee('Riwayat (audit trail)')
        ->call('remind', $document->signers->first()->id)
        ->assertDispatched('notify', type: 'success')
        ->call('remind', $document->signers->first()->id)
        ->assertDispatched('notify', type: 'error')
        ->set('voidReason', 'Ada revisi')
        ->call('void');

    expect($document->fresh()->status)->toBe(DocumentStatus::Voided);
});
