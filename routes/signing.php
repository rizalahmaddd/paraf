<?php

use App\Http\Controllers\SigningController;
use App\Http\Controllers\VerifyDocumentController;
use Illuminate\Support\Facades\Route;

// Zero-login pages for signers and public verifiers. Not behind feature toggles: a link that
// is already in someone's inbox must keep resolving to a readable page.
Route::middleware('throttle:signing')->prefix('sign/{token}')->name('sign.')->controller(SigningController::class)->group(function () {
    Route::get('/', 'show')->name('show');
    Route::post('passcode', 'passcode')->name('passcode');
    Route::get('pdf', 'pdf')->name('pdf');
    Route::get('status', 'status')->name('status');
    Route::post('submit', 'submit')->name('submit');
    Route::post('decline', 'decline')->name('decline');
    Route::get('download', 'download')->name('download');
});

Route::get('verify/{document}', VerifyDocumentController::class)
    ->whereUuid('document')
    ->middleware('throttle:signing')
    ->name('verify.show');
