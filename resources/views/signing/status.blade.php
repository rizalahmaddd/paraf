@php
    $states = [
        'completed' => ['badge-check', 'emerald', 'Dokumen selesai ditandatangani', 'Semua pihak sudah menandatangani. Dokumen final telah disegel bersama lembar audit trail.'],
        'signed' => ['circle-check', 'emerald', 'Terima kasih, tanda tangan Anda sudah diterima', $document->status === \App\Enums\DocumentStatus::Completed ? 'Dokumen sudah selesai.' : 'Anda akan menerima salinan final setelah semua pihak menandatangani.'],
        'declined' => ['circle-x', 'rose', 'Dokumen ditolak', 'Salah satu penandatangan menolak dokumen ini, sehingga tautan penandatanganan dinonaktifkan.'],
        'voided' => ['ban', 'rose', 'Dokumen ditarik kembali oleh pengirim', $document->user->name.' membatalkan dokumen ini. Anda tidak perlu menandatanganinya.'],
        'expired' => ['clock-alert', 'amber', 'Batas waktu sudah lewat', 'Dokumen ini tidak bisa ditandatangani lagi. Hubungi '.$document->user->name.' bila masih diperlukan.'],
        'invalid' => ['link-2-off', 'slate', 'Tautan belum aktif', 'Dokumen ini belum dikirim oleh pengirimnya.'],
    ];
    [$icon, $tone, $heading, $body] = $states[$state];
    $toneClasses = [
        'emerald' => 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400',
        'rose' => 'bg-rose-500/10 border-rose-500/20 text-rose-400',
        'amber' => 'bg-amber-500/10 border-amber-500/20 text-amber-400',
        'slate' => 'bg-slate-800 border-slate-700 text-slate-300',
    ][$tone];
    $canDownload = $document->status === \App\Enums\DocumentStatus::Completed;
    $isSealing = $state === 'signed' && $document->isAwaitingSeal();
@endphp

<x-layouts.public :title="$heading">
    <div class="max-w-md mx-auto" @if ($isSealing) x-data x-init="setTimeout(() => window.location.reload(), 15000)" @endif>
        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-7 shadow-sm dark:shadow-xl space-y-4 text-center">
            <div class="w-12 h-12 mx-auto rounded-full border flex items-center justify-center {{ $toneClasses }}">
                <i data-lucide="{{ $icon }}" class="w-6 h-6"></i>
            </div>
            <h1 class="text-lg font-bold text-slate-100">{{ $heading }}</h1>
            <p class="text-sm text-slate-400">{{ $body }}</p>
            <p class="text-xs text-slate-400">“{{ $document->title }}” · dari {{ $document->user->name }}</p>

            @if ($isSealing)
                <p class="text-xs text-sky-400 inline-flex items-center gap-2 justify-center"><x-spinner class="w-4 h-4" /> Semua pihak sudah tanda tangan. PDF final sedang disegel…</p>
            @endif

            @if ($canDownload)
                <div class="flex flex-col gap-2 pt-2">
                    <a href="{{ route('sign.download', $token) }}" class="inline-flex items-center justify-center gap-2 min-h-[44px] px-4 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-xs font-bold text-slate-950">
                        <i data-lucide="download" class="w-4 h-4"></i> Unduh Salinan Final
                    </a>
                    <a href="{{ route('verify.show', $document) }}" class="inline-flex items-center justify-center gap-2 min-h-[44px] px-4 rounded-lg border border-slate-700 text-xs font-semibold text-slate-300 hover:bg-slate-800">
                        <i data-lucide="shield-check" class="w-4 h-4"></i> Cek Keaslian Dokumen
                    </a>
                </div>
            @endif
        </section>
    </div>
</x-layouts.public>
