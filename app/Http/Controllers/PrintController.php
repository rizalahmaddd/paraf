<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use Illuminate\Contracts\View\View;

/**
 * Dokumen cetak: halaman Blade biasa (bukan Livewire) yang dibuka di tab baru lalu dicetak atau
 * disimpan PDF lewat window.print() bawaan browser, memakai layout x-layouts.print.
 */
class PrintController extends Controller
{
    public function customer(Customer $customer): View
    {
        return view('print.customer', ['customer' => $customer]);
    }
}
