<?php

use App\Enums\AuditEvent;
use App\Enums\DocumentStatus;
use App\Enums\FieldType;
use App\Enums\SignerStatus;
use App\Events\DocumentCompleted;
use App\Jobs\ProcessCompletedDocumentPdf;
use App\Models\Signer;
use App\Models\User;
use App\Notifications\DocumentDeclinedNotification;
use App\Notifications\DocumentSignedNotification;
use App\Notifications\SignatureRequestNotification;
use App\Services\DocumentStorage;
use App\Services\DocumentWorkflow;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
});

function payloadFor(Signer $signer, array $overrides = []): array
{
    $fields = [];
    foreach ($signer->fields as $field) {
        $fields[$field->id] = match ($field->field_type) {
            FieldType::Signature, FieldType::Initial => samplePngDataUrl(),
            FieldType::Text => '3201010101010001',
            FieldType::Checkbox => '1',
            default => null,
        };
    }

    return ['fields' => array_filter($overrides + $fields, fn ($value) => $value !== null), 'consent' => true];
}

test('an unknown token shows a friendly page instead of an error', function () {
    $this->get(route('sign.show', str_repeat('a', 64)))
        ->assertNotFound()
        ->assertSee('Tautan tidak berlaku');
});

test('the signer opens the document without logging in', function () {
    [$document, $tokens] = sentDocument([['name' => 'Budi']]);

    $this->get(route('sign.show', $tokens['Budi']))
        ->assertOk()
        ->assertSee($document->title)
        ->assertSee('Halo Budi');

    $signer = $document->signers->first()->fresh();
    expect($signer->status)->toBe(SignerStatus::Viewed)
        ->and($document->auditLogs()->where('event_type', AuditEvent::Viewed)->count())->toBe(1);

    $this->get(route('sign.pdf', $tokens['Budi']))->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

test('a passcode protected link asks for the passcode and locks after five wrong attempts', function () {
    [$document, $tokens] = sentDocument([['name' => 'Budi', 'passcode' => '246810']]);
    $token = $tokens['Budi'];

    $this->get(route('sign.show', $token))->assertSee('Masukkan passcode');
    $this->get(route('sign.pdf', $token))->assertForbidden();

    foreach (range(1, 5) as $attempt) {
        $this->post(route('sign.passcode', $token), ['passcode' => '000000'])->assertSessionHasErrors('passcode');
    }

    $this->post(route('sign.passcode', $token), ['passcode' => '246810'])
        ->assertSessionHasErrors(['passcode' => 'Terlalu banyak percobaan. Coba lagi dalam 15 menit.']);

    $this->travel(16)->minutes();

    $this->post(route('sign.passcode', $token), ['passcode' => '246810'])->assertSessionHasNoErrors();
    $this->get(route('sign.show', $token))->assertSee('Halo Budi');
    expect($document->auditLogs()->where('event_type', AuditEvent::PasscodeFailed)->count())->toBe(5);
});

test('a sequential signer waits for their turn', function () {
    [$document, $tokens] = sentDocument([['name' => 'Budi'], ['name' => 'Siti']], sequential: true);

    $this->get(route('sign.show', $tokens['Siti']))
        ->assertOk()
        ->assertSee('Menunggu giliran')
        ->assertSee('Budi');

    $this->postJson(route('sign.submit', $tokens['Siti']), payloadFor($document->signers[1]))->assertStatus(422);
});

test('submitting a field of another signer is forbidden', function () {
    [$document, $tokens] = sentDocument([['name' => 'Budi'], ['name' => 'Siti']]);
    $siti = $document->signers->firstWhere('name', 'Siti');
    $budi = $document->signers->firstWhere('name', 'Budi');

    $payload = payloadFor($budi);
    $payload['fields'][$siti->fields->first()->id] = samplePngDataUrl();

    $this->postJson(route('sign.submit', $tokens['Budi']), $payload)->assertForbidden();
    expect($budi->fresh()->status)->not->toBe(SignerStatus::Signed);
});

test('required fields and consent are validated', function () {
    [$document, $tokens] = sentDocument([['name' => 'Budi', 'fields' => [FieldType::Signature, FieldType::Text]]]);
    $budi = $document->signers->first();

    $this->postJson(route('sign.submit', $tokens['Budi']), ['fields' => [], 'consent' => true])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['fields.'.$budi->fields[0]->id, 'fields.'.$budi->fields[1]->id]);

    $this->postJson(route('sign.submit', $tokens['Budi']), ['consent' => false] + payloadFor($budi))
        ->assertStatus(422)
        ->assertJsonValidationErrors('consent');

    $bad = payloadFor($budi);
    $bad['fields'][$budi->fields[0]->id] = 'data:image/png;base64,bm90LWFuLWltYWdl';
    $this->postJson(route('sign.submit', $tokens['Budi']), $bad)->assertStatus(422);
});

test('first signer signs: values are stored, status becomes partially signed, the next signer is invited', function () {
    [$document, $tokens] = sentDocument([['name' => 'Budi', 'fields' => [FieldType::Signature, FieldType::Date, FieldType::Name, FieldType::Checkbox]], ['name' => 'Siti']], sequential: true);
    $budi = $document->signers->firstWhere('name', 'Budi');
    $document->signers->firstWhere('name', 'Siti')->update(['invited_at' => null]);

    $this->postJson(route('sign.submit', $tokens['Budi']), payloadFor($budi))
        ->assertOk()
        ->assertJson(['ok' => true]);

    $budi->refresh();
    $values = $budi->fields->keyBy(fn ($field) => $field->field_type->value);
    expect($budi->status)->toBe(SignerStatus::Signed)
        ->and($budi->signed_ip_address)->toBe('127.0.0.1')
        ->and($document->fresh()->status)->toBe(DocumentStatus::PartiallySigned)
        ->and($values['NAME']->field_value)->toBe('Budi')
        ->and($values['DATE']->field_value)->toBe(now()->locale('id')->isoFormat('D MMMM YYYY'))
        ->and($values['CHECKBOX']->field_value)->toBe('1')
        ->and(Storage::disk('local')->exists($values['SIGNATURE']->field_value))->toBeTrue();

    Notification::assertSentOnDemand(SignatureRequestNotification::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'siti@example.test');
    Notification::assertSentTo($document->user, DocumentSignedNotification::class);

    $this->get(route('sign.show', $tokens['Budi']))->assertSee('tanda tangan Anda sudah diterima');
    $this->postJson(route('sign.submit', $tokens['Budi']), payloadFor($budi))->assertOk();
});

test('the last signature fires DocumentCompleted which queues the sealing job', function () {
    Queue::fake();
    [$document, $tokens] = sentDocument([['name' => 'Budi'], ['name' => 'Siti']]);
    $document->signers->firstWhere('name', 'Budi')->update(['status' => SignerStatus::Signed, 'signed_at' => now()]);

    $this->postJson(route('sign.submit', $tokens['Siti']), payloadFor($document->signers->firstWhere('name', 'Siti')))->assertOk();

    Queue::assertPushed(ProcessCompletedDocumentPdf::class, fn ($job) => $job->document->is($document));
    expect($document->fresh()->isAwaitingSeal())->toBeTrue();
});

test('declining closes the document for everyone and tells the owner why', function () {
    Event::fake([DocumentCompleted::class]);
    [$document, $tokens] = sentDocument([['name' => 'Budi'], ['name' => 'Siti']]);

    $this->postJson(route('sign.decline', $tokens['Budi']), ['reason' => 'Nilai kontrak belum sesuai'])->assertOk();

    expect($document->fresh()->status)->toBe(DocumentStatus::Declined)
        ->and($document->signers->firstWhere('name', 'Budi')->fresh()->decline_reason)->toBe('Nilai kontrak belum sesuai');
    Notification::assertSentTo($document->user, DocumentDeclinedNotification::class);

    $this->get(route('sign.show', $tokens['Siti']))->assertOk()->assertSee('Dokumen ditolak');
    $this->postJson(route('sign.submit', $tokens['Siti']), payloadFor($document->signers->firstWhere('name', 'Siti')))->assertStatus(422);
});

test('voided and expired links show a status page, and polling reports the change', function () {
    [$document, $tokens] = sentDocument([['name' => 'Budi']]);

    app(DocumentWorkflow::class)->void($document, $document->user, 'Salah dokumen');

    $this->getJson(route('sign.status', $tokens['Budi']))->assertJson(['closed' => true, 'document_status' => 'VOIDED']);
    $this->get(route('sign.show', $tokens['Budi']))->assertOk()->assertSee('ditarik kembali oleh pengirim');

    [$expired, $expiredTokens] = sentDocument([['name' => 'Ani']]);
    $expired->update(['expires_at' => now()->subMinute()]);
    $this->get(route('sign.show', $expiredTokens['Ani']))->assertOk()->assertSee('Batas waktu sudah lewat');
});

test('correcting a contact replaces the link', function () {
    [$document, $tokens] = sentDocument([['name' => 'Budi']]);
    $signer = $document->signers->first();

    app(DocumentWorkflow::class)->updateContactAndResend($signer, ['name' => 'Budi Santoso', 'email' => 'budi.s@example.test', 'phone' => null], $document->user);

    $this->get(route('sign.show', $tokens['Budi']))->assertNotFound();
    $this->get($signer->fresh()->signingUrl())->assertOk()->assertSee('Halo Budi Santoso');
    Notification::assertSentOnDemand(SignatureRequestNotification::class, fn ($n, $c, $notifiable) => $notifiable->routes['mail'] === 'budi.s@example.test');
});

test('a saved specimen is offered only to the logged-in account that owns it', function () {
    [, $tokens] = sentDocument([['name' => 'Budi', 'email' => 'budi@example.test']]);

    $account = User::factory()->create(['email' => 'budi@example.test']);
    $path = "users/{$account->id}/signatures/signature.png";
    app(DocumentStorage::class)->put($path, base64_decode(substr(samplePngDataUrl(), 22)));
    $account->update(['saved_signature_path' => $path]);

    $this->get(route('sign.show', $tokens['Budi']))
        ->assertOk()
        ->assertViewHas('config', fn (array $config) => $config['savedSignature'] === null);

    $this->actingAs($account)->get(route('sign.show', $tokens['Budi']))
        ->assertOk()
        ->assertViewHas('config', fn (array $config) => str_starts_with((string) $config['savedSignature'], 'data:image/png;base64,'));
});
