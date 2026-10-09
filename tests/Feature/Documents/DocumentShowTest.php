<?php

use App\Enums\DocumentStatus;
use App\Livewire\Documents\DocumentIndex;
use App\Livewire\Documents\DocumentShow;
use App\Models\User;
use App\Services\DocumentStorage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
});

test('owner can view document show page with live preview config', function () {
    [$document] = sentDocument([['name' => 'Budi Santoso'], ['name' => 'Siti Rahma']]);
    $this->actingAs($document->user);

    $component = Livewire::test(DocumentShow::class, ['document' => $document])
        ->assertOk()
        ->assertSee($document->title)
        ->assertSee('Budi Santoso')
        ->assertSee('Siti Rahma')
        ->assertSee('Pratinjau Dokumen')
        ->assertSee('Riwayat (audit trail)')
        ->assertViewHas('previewConfig', function (array $config) use ($document) {
            return $config['status'] === $document->status->value
                && count($config['pages']) === 1
                && count($config['signers']) === 2
                && ! empty($config['pdfUrl']);
        });

    $this->get(route('documents.show', $document))
        ->assertOk()
        ->assertSee($document->title);
});

test('owner can stream pdf preview and access original and final variants', function () {
    [$document] = sentDocument([['name' => 'Budi Santoso']]);
    $this->actingAs($document->user);

    // Default preview returns original when not completed
    $response = $this->get(route('documents.pdf', $document));
    $response->assertOk();
    expect($response->headers->get('content-type'))->toBe('application/pdf');

    // Create completed version
    $completedBytes = samplePdf(1);
    $completedPath = "documents/{$document->id}/completed.pdf";
    app(DocumentStorage::class)->put($completedPath, $completedBytes);
    $document->update([
        'status' => DocumentStatus::Completed,
        'completed_pdf_path' => $completedPath,
    ]);

    // Preview defaults to final completed pdf when completed
    $finalResponse = $this->get(route('documents.pdf', $document));
    $finalResponse->assertOk();

    // Query variant=original explicitly returns original pdf
    $originalResponse = $this->get(route('documents.pdf', ['document' => $document, 'variant' => 'original']));
    $originalResponse->assertOk();
});

test('unauthorized user cannot access document show or preview pdf', function () {
    [$document] = sentDocument([['name' => 'Budi Santoso']]);
    $otherUser = User::factory()->create();
    $otherUser->assignRole(seededRole('owner'));

    $this->actingAs($otherUser);

    $this->get(route('documents.show', $document))->assertForbidden();
    $this->get(route('documents.pdf', $document))->assertForbidden();
});

test('owner can toggle between tile and list view mode on document index', function () {
    [$document] = sentDocument([['name' => 'Budi Santoso']]);
    $this->actingAs($document->user);

    Livewire::test(DocumentIndex::class)
        ->assertOk()
        ->assertSet('viewMode', 'tile')
        ->assertSee($document->title)
        ->call('setViewMode', 'list')
        ->assertSet('viewMode', 'list')
        ->assertSee($document->title)
        ->assertSee('Dokumen')
        ->assertSee('Penandatangan')
        ->call('setViewMode', 'tile')
        ->assertSet('viewMode', 'tile');
});

test('owner can delete a closed document only after typing HAPUS', function () {
    [$document] = sentDocument([['name' => 'Budi Santoso']]);
    $document->update(['status' => DocumentStatus::Voided, 'voided_at' => now()]);
    $this->actingAs($document->user);

    $component = Livewire::test(DocumentShow::class, ['document' => $document])
        ->set('deleteConfirmation', 'hapus')
        ->call('deleteDocument')
        ->assertHasErrors('deleteConfirmation');

    expect($document->fresh())->not->toBeNull();

    $component->set('deleteConfirmation', 'HAPUS')
        ->call('deleteDocument')
        ->assertRedirect(route('documents.index'));

    expect($document->fresh())->toBeNull()
        ->and(app(DocumentStorage::class)->exists($document->original_pdf_path))->toBeFalse();
});

test('a document in progress cannot be deleted from the list', function () {
    [$document] = sentDocument([['name' => 'Budi Santoso']]);
    $this->actingAs($document->user);

    Livewire::test(DocumentIndex::class)
        ->call('confirmDeleteDocument', $document->id)
        ->set('deleteConfirmation', 'HAPUS')
        ->call('deleteDocument')
        ->assertDispatched('notify', type: 'error');

    expect($document->fresh())->not->toBeNull();
});

test('owner cannot delete another user document from the list', function () {
    [$document] = sentDocument([['name' => 'Budi Santoso']]);
    $document->update(['status' => DocumentStatus::Completed]);
    $this->actingAs(User::factory()->create()->assignRole('owner'));

    expect(fn () => Livewire::test(DocumentIndex::class)
        ->call('confirmDeleteDocument', $document->id)
        ->set('deleteConfirmation', 'HAPUS')
        ->call('deleteDocument'))->toThrow(ModelNotFoundException::class)
        ->and($document->fresh())->not->toBeNull();
});
