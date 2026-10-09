@php
    use App\Enums\DocumentStatus;
    use App\Enums\AuditEvent;
    use Illuminate\Support\Str;

    $isSealed = $document->status === DocumentStatus::Completed;
    $isVoided = $document->status === DocumentStatus::Voided;

    $mask = function (?string $email): ?string {
        if (! $email || ! str_contains($email, '@')) {
            return null;
        }
        [$local, $domain] = explode('@', $email, 2);

        return Str::substr($local, 0, 2).str_repeat('•', max(1, Str::length($local) - 2)).'@'.$domain;
    };
@endphp

<x-layouts.public title="Verifikasi Dokumen: {{ $document->title }}">
    <x-slot:header>
        <div class="flex items-center gap-2">
            <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded-md text-[11px] font-semibold tracking-wide uppercase bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                <i data-lucide="shield-check" class="w-3.5 h-3.5"></i>
                Verifikasi Resmi
            </span>
            <span class="text-xs text-slate-400 font-medium hidden sm:inline">· {{ \App\Support\Branding::appName() }}</span>
        </div>
    </x-slot:header>

    <x-slot:actions>
        <div x-data="{ copied: false }">
            <button
                type="button"
                x-on:click="navigator.clipboard.writeText(window.location.href); copied = true; setTimeout(() => copied = false, 2500)"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-700 bg-slate-800 text-xs font-medium text-slate-200 hover:bg-slate-700 transition"
            >
                <i :data-lucide="copied ? 'check' : 'share-2'" class="w-3.5 h-3.5 text-slate-300"></i>
                <span x-text="copied ? 'Tautan Disalin!' : 'Bagikan'">Bagikan</span>
            </button>
        </div>
    </x-slot:actions>

    <div class="max-w-3xl mx-auto space-y-6">

        {{-- Hero Certificate Card --}}
        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-7 shadow-sm dark:shadow-xl relative overflow-hidden">
            <div class="absolute -top-12 -right-12 w-48 h-48 bg-emerald-500/5 rounded-full blur-3xl pointer-events-none"></div>

            <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                <div @class([
                    'w-14 h-14 rounded-2xl border flex items-center justify-center shrink-0 shadow-inner',
                    'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' => $isSealed,
                    'bg-amber-500/10 border-amber-500/20 text-amber-400' => (! $isSealed && ! $isVoided),
                    'bg-rose-500/10 border-rose-500/20 text-rose-400' => $isVoided,
                ])>
                    <i data-lucide="{{ $isSealed ? 'shield-check' : ($isVoided ? 'shield-x' : 'shield-alert') }}" class="w-7 h-7"></i>
                </div>

                <div class="min-w-0 flex-1 space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <x-document-status-badge :status="$document->status" />
                        @if ($isSealed)
                            <span class="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-400 bg-emerald-500/10 border border-emerald-500/20 px-2 py-0.5 rounded-full">
                                <i data-lucide="lock" class="w-3 h-3"></i> Tersegel Kriptografis
                            </span>
                        @endif
                    </div>

                    <h1 class="text-xl sm:text-2xl font-bold text-slate-100 tracking-tight break-words">
                        {{ $document->title }}
                    </h1>

                    <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
                        @if ($isSealed)
                            Dokumen ini telah sah ditandatangani oleh semua pihak dan disegel secara kriptografis pada <span class="text-slate-100 font-semibold">{{ $document->completed_at?->isoFormat('D MMMM Y, HH:mm:ss') }} WIB</span>. Keaslian isi file terjamin dan dapat dibuktikan keabsahannya.
                        @elseif ($isVoided)
                            Dokumen ini telah dibatalkan pada {{ $document->voided_at?->isoFormat('D MMMM Y, HH:mm') }} WIB dan tidak lagi berlaku sebagai dokumen sah.
                        @else
                            Dokumen ini terdaftar dalam sistem dan sedang dalam proses penandatanganan oleh para pihak.
                        @endif
                    </p>

                    <div class="pt-2 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-slate-400 border-t border-slate-800/80">
                        <div class="flex items-center gap-1.5">
                            <i data-lucide="file-text" class="w-3.5 h-3.5 text-slate-400"></i>
                            <span>{{ $document->total_pages }} Halaman</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <i data-lucide="users" class="w-3.5 h-3.5 text-slate-400"></i>
                            <span>{{ $document->signers->count() }} Pihak Penandatangan</span>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <i data-lucide="calendar" class="w-3.5 h-3.5 text-slate-400"></i>
                            <span>Dibuat {{ $document->created_at->isoFormat('D MMMM Y') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        {{-- Interactive Local PDF Verifier --}}
        @if ($isSealed)
            <section
                class="rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-6 shadow-sm space-y-4"
                x-data="verifyFile(@js(['completed' => $document->completed_hash_sha256, 'original' => $document->original_hash_sha256]))"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold text-slate-100 flex items-center gap-2">
                            <i data-lucide="file-search" class="w-5 h-5 text-emerald-600 dark:text-emerald-400"></i>
                            Uji Keaslian File Anda
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Punya salinan PDF dokumen ini? Seret atau pilih file untuk memastikan isinya tidak pernah diubah atau dimanipulasi.
                        </p>
                    </div>
                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-medium bg-slate-800 text-slate-300 border border-slate-700 shrink-0">
                        <i data-lucide="lock" class="w-3 h-3 text-emerald-600 dark:text-emerald-400"></i> 100% Privat
                    </span>
                </div>

                {{-- Dropzone --}}
                <div
                    x-show="state === 'idle' || state === 'hashing'"
                    class="relative"
                >
                    <label
                        class="flex flex-col items-center justify-center gap-3 p-6 sm:p-8 rounded-xl border-2 border-dashed transition-all cursor-pointer text-center"
                        :class="isDragging ? 'border-emerald-500 bg-emerald-500/10 ring-4 ring-emerald-500/20' : 'border-slate-800 bg-slate-950/40 hover:border-slate-700 hover:bg-slate-950/60'"
                        x-on:dragover.prevent="isDragging = true"
                        x-on:dragleave.prevent="isDragging = false"
                        x-on:drop.prevent="check($event)"
                    >
                        <div class="w-12 h-12 rounded-xl bg-slate-800/80 border border-slate-700/80 flex items-center justify-center text-slate-300 shadow-sm">
                            <template x-if="state === 'hashing'">
                                <svg class="animate-spin w-6 h-6 text-emerald-600 dark:text-emerald-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </template>
                            <template x-if="state !== 'hashing'">
                                <i data-lucide="upload-cloud" class="w-6 h-6 text-slate-400"></i>
                            </template>
                        </div>

                        <div>
                            <p class="text-sm font-semibold text-slate-200">
                                <span x-show="state !== 'hashing'">Seret dan lepas file PDF ke sini, atau klik untuk memilih</span>
                                <span x-show="state === 'hashing'" class="text-emerald-600 dark:text-emerald-400">Menghitung sidik jari SHA-256 dokumen...</span>
                            </p>
                            <p class="text-xs text-slate-400 mt-1">
                                Hash dihitung secara aman di peramban Anda melalui Web Crypto API. File PDF tidak pernah diunggah ke internet.
                            </p>
                        </div>

                        <input type="file" accept="application/pdf,.pdf" class="sr-only" x-on:change="check($event)" :disabled="state === 'hashing'">
                    </label>
                </div>

                {{-- Result States --}}
                <div x-show="state === 'match'" x-cloak class="rounded-xl border border-emerald-500/40 bg-emerald-500/10 dark:bg-emerald-500/10 p-5 space-y-3">
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-lg bg-emerald-500/20 border border-emerald-500/30 text-emerald-700 dark:text-emerald-400 flex items-center justify-center shrink-0">
                            <i data-lucide="check-circle-2" class="w-5 h-5"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-sm font-bold text-emerald-950 dark:text-emerald-200">Dokumen Cocok dan Terbukti Asli!</h3>
                            <p class="text-xs text-emerald-900 dark:text-emerald-200/90 mt-0.5 leading-relaxed">
                                File PDF <span class="font-bold text-emerald-950 dark:text-emerald-100" x-text="fileName"></span> (<span class="font-medium" x-text="fileSize"></span>) identik 100% bit per bit dengan dokumen final yang disegel resmi oleh {{ \App\Support\Branding::appName() }}.
                            </p>
                        </div>
                    </div>

                    <div class="rounded-lg bg-slate-950/60 border border-emerald-500/30 p-3 text-xs space-y-1 font-mono">
                        <div class="text-[11px] text-emerald-800 dark:text-emerald-400 font-sans font-semibold">Sidik Jari SHA-256 Terverifikasi:</div>
                        <div class="text-slate-100 dark:text-slate-200 break-all select-all font-medium" x-text="hash"></div>
                    </div>

                    <div class="pt-1 flex justify-end">
                        <button type="button" x-on:click="reset()" class="text-xs font-semibold text-emerald-700 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300 underline underline-offset-4">
                            Uji File Lain
                        </button>
                    </div>
                </div>

                <div x-show="state === 'original'" x-cloak class="rounded-xl border border-amber-500/40 bg-amber-500/10 dark:bg-amber-500/10 p-5 space-y-3">
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-lg bg-amber-500/20 border border-amber-500/30 text-amber-700 dark:text-amber-400 flex items-center justify-center shrink-0">
                            <i data-lucide="info" class="w-5 h-5"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-sm font-bold text-amber-950 dark:text-amber-200">File Dokumen Asli (Sebelum Ditandatangani)</h3>
                            <p class="text-xs text-amber-900 dark:text-amber-200/90 mt-0.5 leading-relaxed">
                                File ini cocok dengan dokumen versi awal saat pertama kali diunggah, bukan dokumen final yang telah dibubuhi tanda tangan dan sertifikat segel.
                            </p>
                        </div>
                    </div>

                    <div class="pt-1 flex justify-end">
                        <button type="button" x-on:click="reset()" class="text-xs font-semibold text-amber-700 hover:text-amber-800 dark:text-amber-400 dark:hover:text-amber-300 underline underline-offset-4">
                            Uji File Lain
                        </button>
                    </div>
                </div>

                <div x-show="state === 'mismatch'" x-cloak class="rounded-xl border border-rose-500/40 bg-rose-500/10 dark:bg-rose-500/10 p-5 space-y-3">
                    <div class="flex items-start gap-3">
                        <div class="w-9 h-9 rounded-lg bg-rose-500/20 border border-rose-500/30 text-rose-700 dark:text-rose-400 flex items-center justify-center shrink-0">
                            <i data-lucide="alert-triangle" class="w-5 h-5"></i>
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-sm font-bold text-rose-950 dark:text-rose-200">Sidik Jari (Hash) Tidak Cocok</h3>
                            <p class="text-xs text-rose-900 dark:text-rose-200/90 mt-0.5 leading-relaxed">
                                File PDF <span class="font-bold text-rose-950 dark:text-rose-100" x-text="fileName"></span> tidak cocok dengan rekaman dokumen resmi di sistem kami. Isinya kemungkinan telah dimodifikasi, diedit, atau bukan file yang dimaksud.
                            </p>
                        </div>
                    </div>

                    <div class="rounded-lg bg-slate-950/60 border border-rose-500/30 p-3 text-xs space-y-1 font-mono">
                        <div class="text-[11px] text-rose-800 dark:text-rose-400 font-sans font-semibold">Hash File Anda:</div>
                        <div class="text-slate-100 dark:text-slate-200 break-all select-all font-medium" x-text="hash"></div>
                    </div>

                    <div class="pt-1 flex justify-end">
                        <button type="button" x-on:click="reset()" class="text-xs font-semibold text-rose-700 hover:text-rose-800 dark:text-rose-400 dark:hover:text-rose-300 underline underline-offset-4">
                            Uji File Lain
                        </button>
                    </div>
                </div>

                <div x-show="state === 'unsupported'" x-cloak class="rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-xs text-amber-900 dark:text-amber-200 font-medium">
                    Peramban Anda tidak mendukung kalkulasi hash Web Crypto. Silakan gunakan peramban modern atau buka melalui koneksi aman (HTTPS).
                </div>
            </section>
        @endif

        {{-- Cryptographic Integrity & Hash Details --}}
        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-100 flex items-center gap-2">
                        <i data-lucide="binary" class="w-5 h-5 text-indigo-400"></i>
                        Sidik Jari Kriptografis (Cryptographic Hash)
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Catatan integritas dokumen yang tersimpan permanen dan tidak dapat diubah (tamper-evident).
                    </p>
                </div>
            </div>

            <div class="space-y-3 pt-1">
                {{-- Document UUID --}}
                <div x-data="{ copied: false }" class="p-3.5 rounded-xl border border-slate-800 bg-slate-950/40 hover:bg-slate-950/60 transition space-y-1">
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-400 font-medium flex items-center gap-1.5">
                            <i data-lucide="key" class="w-3.5 h-3.5 text-slate-400"></i>
                            ID Unik Dokumen (UUID)
                        </span>
                        <button
                            type="button"
                            x-on:click="navigator.clipboard.writeText('{{ $document->id }}'); copied = true; setTimeout(() => copied = false, 2000)"
                            class="text-[11px] font-medium text-slate-400 hover:text-slate-200 flex items-center gap-1"
                        >
                            <i :data-lucide="copied ? 'check' : 'copy'" class="w-3 h-3"></i>
                            <span x-text="copied ? 'Tersalin!' : 'Salin'">Salin</span>
                        </button>
                    </div>
                    <p class="font-mono text-xs text-slate-200 select-all break-all">{{ $document->id }}</p>
                </div>

                {{-- Original Hash --}}
                @if ($document->original_hash_sha256)
                    <div x-data="{ copied: false }" class="p-3.5 rounded-xl border border-slate-800 bg-slate-950/40 hover:bg-slate-950/60 transition space-y-1">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-400 font-medium flex items-center gap-1.5">
                                <i data-lucide="file-up" class="w-3.5 h-3.5 text-slate-400"></i>
                                SHA-256 Dokumen Awal (Sebelum Penandatanganan)
                            </span>
                            <button
                                type="button"
                                x-on:click="navigator.clipboard.writeText('{{ $document->original_hash_sha256 }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="text-[11px] font-medium text-slate-400 hover:text-slate-200 flex items-center gap-1"
                            >
                                <i :data-lucide="copied ? 'check' : 'copy'" class="w-3 h-3"></i>
                                <span x-text="copied ? 'Tersalin!' : 'Salin'">Salin</span>
                            </button>
                        </div>
                        <p class="font-mono text-xs text-slate-300 select-all break-all">{{ $document->original_hash_sha256 }}</p>
                    </div>
                @endif

                {{-- Signed Hash --}}
                @if ($document->signed_hash_sha256)
                    <div x-data="{ copied: false }" class="p-3.5 rounded-xl border border-slate-800 bg-slate-950/40 hover:bg-slate-950/60 transition space-y-1">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-slate-400 font-medium flex items-center gap-1.5">
                                <i data-lucide="pen-tool" class="w-3.5 h-3.5 text-slate-400"></i>
                                SHA-256 Halaman Bertanda Tangan (Sebelum Lembar Sertifikat)
                            </span>
                            <button
                                type="button"
                                x-on:click="navigator.clipboard.writeText('{{ $document->signed_hash_sha256 }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="text-[11px] font-medium text-slate-400 hover:text-slate-200 flex items-center gap-1"
                            >
                                <i :data-lucide="copied ? 'check' : 'copy'" class="w-3 h-3"></i>
                                <span x-text="copied ? 'Tersalin!' : 'Salin'">Salin</span>
                            </button>
                        </div>
                        <p class="font-mono text-xs text-slate-300 select-all break-all">{{ $document->signed_hash_sha256 }}</p>
                    </div>
                @endif

                {{-- Completed Sealed Hash --}}
                @if ($document->completed_hash_sha256)
                    <div x-data="{ copied: false }" class="p-3.5 rounded-xl border border-emerald-500/30 bg-emerald-500/5 hover:bg-emerald-500/10 transition space-y-1">
                        <div class="flex items-center justify-between text-xs">
                            <span class="text-emerald-800 dark:text-emerald-400 font-medium flex items-center gap-1.5">
                                <i data-lucide="shield-check" class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400"></i>
                                SHA-256 File Final Tersegel (Termasuk Lembar Sertifikat Audit)
                            </span>
                            <button
                                type="button"
                                x-on:click="navigator.clipboard.writeText('{{ $document->completed_hash_sha256 }}'); copied = true; setTimeout(() => copied = false, 2000)"
                                class="text-[11px] font-medium text-emerald-700 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300 flex items-center gap-1"
                            >
                                <i :data-lucide="copied ? 'check' : 'copy'" class="w-3 h-3"></i>
                                <span x-text="copied ? 'Tersalin!' : 'Salin'">Salin</span>
                            </button>
                        </div>
                        <p class="font-mono text-xs text-emerald-800 dark:text-emerald-300 select-all break-all font-semibold">{{ $document->completed_hash_sha256 }}</p>
                    </div>
                @endif
            </div>

            <p class="text-[11px] text-slate-400 leading-relaxed border-t border-slate-800/80 pt-3">
                <span class="font-semibold text-slate-300">Prinsip Keamanan:</span> Hashing berantai memastikan bahwa perubahan sekecil apa pun pada teks, tanda tangan, atau lampiran dokumen akan mengubah nilai SHA-256 secara drastis, membuktikan bila ada upaya pemalsuan.
            </p>
        </section>

        {{-- Signatories Section --}}
        <section class="rounded-2xl border border-slate-800 bg-slate-900 overflow-hidden shadow-sm">
            <div class="px-5 py-4 border-b border-slate-800 flex items-center justify-between">
                <div>
                    <h2 class="text-base font-bold text-slate-100 flex items-center gap-2">
                        <i data-lucide="users" class="w-5 h-5 text-sky-400"></i>
                        Para Pihak Penandatangan
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">Daftar pihak yang terdaftar dan status persetujuannya.</p>
                </div>
                <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-slate-800 text-slate-300 border border-slate-700">
                    {{ $document->signers->count() }} Pihak
                </span>
            </div>

            <ul class="divide-y divide-slate-800">
                @foreach ($document->signers as $index => $signer)
                    @php
                        $signed = $signer->status === \App\Enums\SignerStatus::Signed;
                        $initials = collect(explode(' ', $signer->name))
                            ->filter()
                            ->map(fn($w) => Str::upper(Str::substr($w, 0, 1)))
                            ->take(2)
                            ->implode('');
                    @endphp
                    <li class="p-4 sm:p-5 flex items-start justify-between gap-4 hover:bg-slate-800/20 transition">
                        <div class="flex items-start gap-3.5 min-w-0">
                            <div @class([
                                'w-10 h-10 rounded-xl border flex items-center justify-center font-bold text-xs shrink-0',
                                'bg-emerald-500/10 border-emerald-500/20 text-emerald-400' => $signed,
                                'bg-slate-800 border-slate-700 text-slate-300' => ! $signed,
                            ])>
                                {{ $initials ?: '?' }}
                            </div>

                            <div class="min-w-0 space-y-0.5">
                                <div class="flex items-center gap-2 flex-wrap">
                                    <p class="text-sm font-bold text-slate-100">{{ $signer->name }}</p>
                                    @if ($document->signing_order_mode === \App\Enums\SigningOrderMode::Sequential)
                                        <span class="text-[10px] font-medium px-1.5 py-0.2 rounded bg-slate-800 text-slate-400 border border-slate-700">
                                            Urutan {{ $signer->signing_order }}
                                        </span>
                                    @endif
                                </div>

                                @if ($masked = $mask($signer->email))
                                    <p class="text-xs text-slate-400 font-mono flex items-center gap-1.5">
                                        <i data-lucide="mail" class="w-3 h-3 text-slate-400"></i>
                                        <span>{{ $masked }}</span>
                                    </p>
                                @endif

                                @if ($signer->signed_at)
                                    <p class="text-[11px] text-emerald-400 flex items-center gap-1.5 pt-0.5">
                                        <i data-lucide="check" class="w-3.5 h-3.5"></i>
                                        <span>Ditandatangani pada {{ $signer->signed_at->isoFormat('D MMMM Y, HH:mm:ss') }} WIB</span>
                                    </p>
                                @endif
                            </div>
                        </div>

                        <div class="shrink-0 pt-0.5">
                            <x-badge :color="$signer->status->color()">{{ strtoupper($signer->status->label()) }}</x-badge>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- Audit Milestones (Public Safe) --}}
        @if ($document->auditLogs && $document->auditLogs->isNotEmpty())
            <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-6 shadow-sm space-y-4">
                <div>
                    <h2 class="text-base font-bold text-slate-100 flex items-center gap-2">
                        <i data-lucide="history" class="w-5 h-5 text-amber-400"></i>
                        Jejak Rekam Dokumen (Audit Milestones)
                    </h2>
                    <p class="text-xs text-slate-400 mt-0.5">
                        Kronologi penting siklus dokumen yang tercatat dalam sistem secara permanen.
                    </p>
                </div>

                <div class="pt-2">
                    <ul class="space-y-0">
                        @foreach ($document->auditLogs as $log)
                            @php
                                $isCompleted = $log->event_type === AuditEvent::Completed;
                                $isSigned = $log->event_type === AuditEvent::Signed;
                            @endphp
                            <li class="flex items-stretch gap-3.5">
                                {{-- Centered Indicator & Connecting Line --}}
                                <div class="flex flex-col items-center shrink-0 w-7">
                                    <div @class([
                                        'w-7 h-7 rounded-full border flex items-center justify-center shrink-0 shadow-sm z-10',
                                        'bg-emerald-500 border-emerald-400 text-slate-950 font-bold' => $isCompleted,
                                        'bg-slate-900 border-emerald-500 text-emerald-500' => $isSigned,
                                        'bg-slate-900 border-slate-700 text-slate-400' => (! $isCompleted && ! $isSigned),
                                    ])>
                                        <i data-lucide="{{ $log->event_type->icon() }}" class="w-3.5 h-3.5"></i>
                                    </div>
                                    @if (! $loop->last)
                                        <div class="w-0.5 flex-1 bg-slate-700"></div>
                                    @endif
                                </div>

                                {{-- Content --}}
                                <div @class(['min-w-0 flex-1 pt-1', 'pb-6' => ! $loop->last, 'pb-1' => $loop->last])>
                                    <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-0.5">
                                        <p class="text-xs sm:text-sm font-semibold text-slate-200">
                                            {{ $log->event_type->label() }}
                                            @if ($log->signer)
                                                <span class="text-slate-400 font-normal">oleh {{ $log->signer->name }}</span>
                                            @endif
                                        </p>
                                        <span class="text-[11px] text-slate-400 font-mono">
                                            {{ $log->created_at->isoFormat('D MMM Y, HH:mm') }} WIB
                                        </span>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @endif

        {{-- Legal & Integrity Notice --}}
        <div class="rounded-xl border border-slate-800/80 bg-slate-900/50 p-4 sm:p-5 text-center space-y-2">
            <div class="flex items-center justify-center gap-1.5 text-xs font-semibold text-slate-300">
                <i data-lucide="shield" class="w-4 h-4 text-emerald-400"></i>
                <span>Keabsahan Hukum & Forensik Digital</span>
            </div>
            <p class="text-[11px] text-slate-400 leading-relaxed max-w-xl mx-auto">
                Halaman ini menampilkan catatan verifikasi publik. Informasi jejak forensik audit lengkap (termasuk alamat IP terenkripsi, stempel waktu ISO, agen peramban, dan sertifikat penandatangan) tertanam permanen pada lembar sertifikat di dalam berkas PDF final.
            </p>
        </div>

    </div>
</x-layouts.public>
