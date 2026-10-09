<?php

use App\Livewire\Reports\ActivityLogReport;
use Illuminate\Support\Facades\Route;

// Menu "Laporan". Setiap komponen menggate aksesnya sendiri di mount().
Route::middleware(['auth', 'verified'])->prefix('laporan')->name('reports.')->group(function () {
    Route::get('aktivitas', ActivityLogReport::class)->name('activity-log');
});
