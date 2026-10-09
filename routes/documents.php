<?php

use App\Http\Controllers\DocumentFileController;
use App\Livewire\Documents\DocumentIndex;
use App\Livewire\Documents\DocumentPrepare;
use App\Livewire\Documents\DocumentShow;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:manage-documents'])->prefix('dokumen')->name('documents.')->whereUuid('document')->group(function () {
    Route::get('/', DocumentIndex::class)->name('index');
    Route::get('{document}', DocumentShow::class)->name('show');
    Route::get('{document}/siapkan', DocumentPrepare::class)->name('prepare');
    Route::get('{document}/pdf', [DocumentFileController::class, 'preview'])->name('pdf');
    Route::get('{document}/thumbnail', [DocumentFileController::class, 'thumbnail'])->name('thumbnail');
});

// The temporary signature is the authorization: signers download through the same link.
Route::get('dokumen/{document}/unduh/{variant}', [DocumentFileController::class, 'download'])
    ->middleware('signed')
    ->whereUuid('document')
    ->whereIn('variant', ['original', 'final'])
    ->name('documents.download');
