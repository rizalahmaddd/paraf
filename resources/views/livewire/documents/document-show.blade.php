@php
    use App\Enums\DocumentStatus;
    use App\Enums\SignerStatus;
    $status = $document->status;
    $total = $signers->count();
    $percent = $total ? round($signedCount / $total * 100) : 0;
@endphp

<div class="space-y-4 sm:space-y-6" wire:poll.30s.visible
     x-data="{
         mobileView: 'preview',
         activeTab: 'signers',
     }"
     x-init="@if ($showDistribution) $nextTick(() => $dispatch('open-modal', 'distribution')) @endif">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <a href="{{ route('documents.index') }}" wire:navigate class="inline-flex items-center gap-1.5 text-xs font-medium text-slate-400 hover:text-slate-200 min-h-[44px] sm:min-h-0 transition">
            <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Semua dokumen
        </a>

        {{-- Mobile Switcher (Pratinjau vs Detail) --}}
        <div class="lg:hidden w-full sm:w-auto">
            <x-segmented class="w-full sm:w-auto">
                <x-tab-button size="sm" class="flex-1 sm:flex-initial" icon="file-text"
                    x-bind:class="mobileView === 'preview' ? '!bg-emerald-500/10 !text-emerald-700 dark:!text-emerald-400 !border-emerald-500/30' : ''"
                    x-on:click="mobileView = 'preview'">
                    Pratinjau Dokumen
                </x-tab-button>
                <x-tab-button size="sm" class="flex-1 sm:flex-initial" icon="info"
                    x-bind:class="mobileView === 'details' ? '!bg-emerald-500/10 !text-emerald-700 dark:!text-emerald-400 !border-emerald-500/30' : ''"
                    x-on:click="mobileView = 'details'">
                    Info & Penandatangan
                </x-tab-button>
            </x-segmented>
        </div>
    </div>

    {{-- Main Desktop Split Workbench --}}
    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1.25fr)_400px] xl:grid-cols-[minmax(0,1.35fr)_430px] gap-6 items-start">

        {{-- LEFT COLUMN: Live Document Preview --}}
        <section class="space-y-3" :class="mobileView === 'preview' ? 'block' : 'hidden lg:block'">
            <div class="rounded-2xl border border-slate-800 bg-slate-900 shadow-sm dark:shadow-xl overflow-hidden flex flex-col"
                 x-data="documentPreview(@js($previewConfig))"
                 x-ref="viewerWrapper"
                 wire:ignore>

                {{-- Preview Toolbar --}}
                <header class="px-3.5 py-2.5 border-b border-slate-800 bg-slate-900/95 backdrop-blur flex flex-wrap items-center justify-between gap-2.5 sticky top-0 z-20">
                    <div class="flex items-center gap-2 min-w-0">
                        <span class="w-7 h-7 rounded-lg bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
                            <i data-lucide="file-text" class="w-4 h-4"></i>
                        </span>
                        <div class="min-w-0">
                            <h2 class="text-xs font-bold text-slate-100 truncate" title="{{ $document->title }}">{{ $document->title }}</h2>
                            <p class="text-[11px] text-slate-400">
                                {{ $document->total_pages }} halaman · <span x-text="variant === 'final' ? 'PDF Tersegel' : 'PDF Asli'"></span>
                            </p>
                        </div>
                    </div>

                    {{-- Toolbar Controls --}}
                    <div class="flex flex-wrap items-center gap-1.5 ml-auto">
                        {{-- Variant Switcher (if document completed) --}}
                        <template x-if="config.hasCompleted">
                            <div class="inline-flex items-center p-0.5 rounded-lg bg-slate-950/60 border border-slate-800 text-[11px] mr-1">
                                <button type="button" x-on:click="switchVariant('final')"
                                    :class="variant === 'final' ? 'bg-slate-800 text-emerald-700 dark:text-emerald-400 font-semibold shadow-xs' : 'text-slate-400 hover:text-slate-200'"
                                    class="px-2 py-1 rounded-md transition cursor-pointer">
                                    Tersegel (Final)
                                </button>
                                <button type="button" x-on:click="switchVariant('original')"
                                    :class="variant === 'original' ? 'bg-slate-800 text-slate-100 font-semibold shadow-xs' : 'text-slate-400 hover:text-slate-200'"
                                    class="px-2 py-1 rounded-md transition cursor-pointer">
                                    PDF Asli
                                </button>
                            </div>
                        </template>

                        {{-- Page Navigation --}}
                        <div class="inline-flex items-center gap-1 px-1 py-0.5 rounded-lg border border-slate-800 bg-slate-950/40 text-xs text-slate-200">
                            <button type="button" x-on:click="prevPage()" :disabled="currentPage <= 1" title="Halaman sebelumnya"
                                class="w-6 h-6 rounded flex items-center justify-center text-slate-400 hover:text-slate-100 hover:bg-slate-800 disabled:opacity-30 disabled:pointer-events-none transition cursor-pointer">
                                <i data-lucide="chevron-left" class="w-3.5 h-3.5"></i>
                            </button>
                            <span class="font-mono text-[11px] px-1 select-none text-slate-300">
                                <span x-text="currentPage"></span> / <span x-text="pages.length"></span>
                            </span>
                            <button type="button" x-on:click="nextPage()" :disabled="currentPage >= pages.length" title="Halaman berikutnya"
                                class="w-6 h-6 rounded flex items-center justify-center text-slate-400 hover:text-slate-100 hover:bg-slate-800 disabled:opacity-30 disabled:pointer-events-none transition cursor-pointer">
                                <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>

                        {{-- Zoom Controls --}}
                        <div class="inline-flex items-center gap-0.5 px-1 py-0.5 rounded-lg border border-slate-800 bg-slate-950/40 text-xs">
                            <button type="button" x-on:click="zoomOut()" title="Perkecil zoom (-)"
                                class="w-6 h-6 rounded flex items-center justify-center text-slate-400 hover:text-slate-100 hover:bg-slate-800 transition cursor-pointer">
                                <i data-lucide="zoom-out" class="w-3.5 h-3.5"></i>
                            </button>
                            <button type="button" x-on:click="resetZoom()" title="Reset zoom (100%)"
                                class="font-mono text-[11px] px-1.5 py-0.5 rounded text-slate-300 hover:bg-slate-800 transition cursor-pointer">
                                <span x-text="zoomLevel + '%'"></span>
                            </button>
                            <button type="button" x-on:click="zoomIn()" title="Perbesar zoom (+)"
                                class="w-6 h-6 rounded flex items-center justify-center text-slate-400 hover:text-slate-100 hover:bg-slate-800 transition cursor-pointer">
                                <i data-lucide="zoom-in" class="w-3.5 h-3.5"></i>
                            </button>
                        </div>

                        {{-- Fields Overlay Toggle --}}
                        <template x-if="fields.length > 0">
                            <button type="button" x-on:click="showFields = !showFields"
                                :class="showFields ? 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30 font-semibold' : 'text-slate-400 border-slate-800 hover:text-slate-200 hover:bg-slate-800'"
                                class="inline-flex items-center gap-1.5 min-h-[30px] px-2.5 py-1 rounded-lg border text-[11px] transition cursor-pointer"
                                title="Tampilkan atau sembunyikan kotak tanda tangan">
                                <i data-lucide="layers" class="w-3.5 h-3.5"></i>
                                <span class="hidden sm:inline">Kotak</span>
                            </button>
                        </template>

                        {{-- Fullscreen Toggle --}}
                        <button type="button" x-on:click="toggleFullscreen()"
                            class="w-7 h-7 rounded-lg border border-slate-800 bg-slate-950/40 flex items-center justify-center text-slate-400 hover:text-slate-200 hover:bg-slate-800 transition cursor-pointer"
                            title="Layar penuh">
                            <i :data-lucide="isFullscreen ? 'minimize-2' : 'maximize-2'" class="w-3.5 h-3.5"></i>
                        </button>

                        {{-- Open Raw in New Tab --}}
                        <a :href="currentPdfUrl" target="_blank" rel="noopener"
                            class="w-7 h-7 rounded-lg border border-slate-800 bg-slate-950/40 flex items-center justify-center text-slate-400 hover:text-slate-200 hover:bg-slate-800 transition"
                            title="Buka PDF di tab baru">
                            <i data-lucide="external-link" class="w-3.5 h-3.5"></i>
                        </a>
                    </div>
                </header>

                {{-- Canvas Area --}}
                <div x-ref="canvasContainer"
                     class="p-4 sm:p-6 bg-slate-950/60 overflow-y-auto max-h-[calc(100vh-200px)] min-h-[620px] flex flex-col items-center gap-6 custom-scrollbar relative">

                    {{-- Loading Indicator --}}
                    <div x-show="loading" class="my-auto py-20 text-center text-xs text-slate-400">
                        <x-spinner class="w-7 h-7 mx-auto mb-3 text-emerald-500" />
                        <p class="font-semibold text-slate-200">Memuat pratinjau dokumen...</p>
                        <p class="text-[11px] text-slate-400 mt-1">Merender halaman dengan PDF.js engine</p>
                    </div>

                    {{-- Load Error --}}
                    <div x-show="loadError" x-cloak class="my-auto p-6 rounded-xl border border-rose-500/30 bg-rose-500/10 text-center max-w-sm">
                        <i data-lucide="alert-circle" class="w-6 h-6 mx-auto text-rose-400 mb-2"></i>
                        <p class="text-xs text-rose-300 font-medium" x-text="loadError"></p>
                        <button type="button" x-on:click="reload()" class="mt-3 px-3 py-1.5 rounded-lg bg-rose-600 hover:bg-rose-500 text-xs font-semibold text-white transition cursor-pointer">
                            Muat Ulang
                        </button>
                    </div>

                    {{-- Rendered Pages --}}
                    <div x-show="!loading && !loadError" class="w-full flex flex-col items-center gap-6 transition-opacity duration-300">
                        <template x-for="page in pages" :key="page.number">
                            <div class="flex flex-col items-center w-full">
                                <div class="w-full flex items-center justify-between text-[11px] text-slate-400 mb-1.5 px-1"
                                     :style="{ maxWidth: zoomLevel === 100 ? '820px' : 'none', width: zoomLevel + '%' }">
                                    <span class="font-medium text-slate-300" x-text="'Halaman ' + page.number + ' dari ' + pages.length"></span>
                                    <span class="font-mono text-[10px] text-slate-400" x-text="Math.round(page.width) + ' × ' + Math.round(page.height) + ' pt'"></span>
                                </div>

                                <div :data-page="page.number" :style="pageStyle(page)"
                                     class="relative w-full bg-white rounded-lg shadow-md border border-slate-800/80 overflow-hidden select-none">
                                    {{-- Canvas slot for VirtualPages --}}
                                    <div data-canvas-slot class="absolute inset-0"></div>

                                    {{-- Optional Signature Boxes Overlay --}}
                                    <template x-if="showFields">
                                        <div class="absolute inset-0 pointer-events-none">
                                            <template x-for="field in fieldsOn(page.number)" :key="field.id">
                                                <div class="absolute z-10 rounded-[4px] border-2 flex flex-col justify-between p-1 text-[10px] font-semibold transition"
                                                     :style="fieldStyle(field)">
                                                    <div class="flex items-center justify-between gap-1 leading-none">
                                                        <span class="truncate px-1 py-0.5 rounded text-[9px] font-bold text-white shadow-xs"
                                                              :style="{ background: signer(field.signer_id).color }"
                                                              x-text="signer(field.signer_id).name"></span>
                                                        <template x-if="field.is_filled">
                                                            <span class="px-1 py-0.5 rounded bg-emerald-500 text-slate-950 text-[9px] font-bold">✓ Terisi</span>
                                                        </template>
                                                    </div>
                                                    <span class="truncate text-[10px] px-0.5 opacity-90 font-medium"
                                                          x-text="field.label || fieldLabel(field.type)"></span>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </section>

        {{-- RIGHT COLUMN: Document Intelligence & Actions --}}
        <aside class="space-y-4" :class="mobileView === 'details' ? 'block' : 'hidden lg:block'">

            {{-- Focal Summary & Action Panel --}}
            <section class="rounded-2xl border border-slate-800 bg-slate-900 p-4 sm:p-5 shadow-sm dark:shadow-xl space-y-4">
                <div class="space-y-2">
                    <x-document-status-badge :status="$status" />
                    <h1 class="text-base sm:text-xl font-extrabold text-slate-100 tracking-tight break-words">{{ $document->title }}</h1>
                    <p class="text-xs text-slate-400">
                        {{ $document->total_pages }} halaman · alur {{ strtolower($document->signing_order_mode->label()) }}
                        · dikirim {{ $document->sent_at?->isoFormat('D MMM Y, HH:mm') }}
                        @if ($status->isInProgress())
                            · berlaku sampai <span @class(['font-semibold', 'text-amber-500' => $document->expires_at?->lt(now()->addDays(3))])>{{ $document->expires_at?->isoFormat('D MMM Y, HH:mm') }}</span>
                        @endif
                    </p>

                    @if ($status === DocumentStatus::Declined)
                        @php $decliner = $signers->firstWhere('status', SignerStatus::Declined); @endphp
                        <p class="text-xs text-rose-400 font-medium">Ditolak oleh {{ $decliner?->name }}: “{{ $decliner?->decline_reason }}”</p>
                    @elseif ($status === DocumentStatus::Voided)
                        <p class="text-xs text-slate-300">Dibatalkan {{ $document->voided_at?->isoFormat('D MMM Y, HH:mm') }}. Semua tautan penandatangan sudah nonaktif.</p>
                    @elseif ($status === DocumentStatus::Expired)
                        <p class="text-xs text-amber-400 font-medium">Batas waktu habis sebelum semua pihak menandatangani.</p>
                    @elseif ($document->isAwaitingSeal())
                        @if ($document->processing_failed_at)
                            <p class="text-xs text-rose-400 font-medium">Semua tanda tangan lengkap, tetapi PDF final gagal dibuat. Tanda tangan tetap tersimpan.</p>
                        @else
                            <p class="text-xs text-sky-400 inline-flex items-center gap-2"><x-spinner class="w-3.5 h-3.5" /> Semua tanda tangan lengkap. PDF final sedang disegel…</p>
                        @endif
                    @endif
                </div>

                {{-- Primary Action Buttons --}}
                <div class="flex flex-col gap-2 pt-1">
                    @if ($status === DocumentStatus::Completed)
                        <x-primary-button type="button" wire:click="download('final')" class="w-full">
                            <i data-lucide="download" class="w-4 h-4"></i> Unduh PDF Final
                        </x-primary-button>
                        <a href="{{ route('verify.show', $document) }}" target="_blank" rel="noopener" class="w-full inline-flex items-center justify-center gap-2 min-h-[44px] sm:min-h-[38px] px-4 py-2 rounded-lg border border-slate-700 bg-slate-800/40 text-xs font-semibold text-slate-200 hover:bg-slate-800 hover:text-slate-100 transition">
                            <i data-lucide="shield-check" class="w-4 h-4 text-emerald-400"></i> Halaman Verifikasi
                        </a>
                    @elseif ($document->isAwaitingSeal() && $document->processing_failed_at)
                        <x-primary-button type="button" wire:click="retrySealing" class="w-full">
                            <i data-lucide="refresh-cw" class="w-4 h-4"></i> <x-loading-label target="retrySealing" loading="Memproses...">Proses Ulang PDF</x-loading-label>
                        </x-primary-button>
                    @elseif ($ownerSigner)
                        <a href="{{ $ownerSigner->signingUrl() }}" class="w-full inline-flex items-center justify-center gap-2 min-h-[44px] px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-xs font-bold text-slate-950 transition">
                            <i data-lucide="pen-line" class="w-4 h-4"></i> Tanda Tangani Sekarang
                        </a>
                    @endif

                    @if ($status->isInProgress())
                        <x-secondary-button type="button" x-on:click="$dispatch('open-modal', 'distribution')" class="w-full">
                            <i data-lucide="share-2" class="w-4 h-4"></i> Bagikan Tautan
                        </x-secondary-button>
                    @endif

                    <x-secondary-button type="button" wire:click="openSaveTemplate" class="w-full">
                        <i data-lucide="copy" class="w-4 h-4"></i> Simpan sebagai Template
                    </x-secondary-button>
                </div>

                {{-- Signing Progress --}}
                <div class="pt-2 border-t border-slate-800">
                    <div class="flex items-center justify-between text-xs text-slate-400 mb-1.5">
                        <span>{{ $signedCount }} dari {{ $total }} penandatangan selesai</span>
                        <span class="font-mono font-medium">{{ $percent }}%</span>
                    </div>
                    <div class="h-2 rounded-full bg-slate-800 overflow-hidden" role="progressbar" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
                        <div class="h-full rounded-full transition-all duration-500 {{ $status === DocumentStatus::Completed ? 'bg-emerald-500' : 'bg-sky-500' }}" style="width: {{ $percent }}%"></div>
                    </div>
                </div>
            </section>

            {{-- Segmented Tabs: Penandatangan, Riwayat, Integritas --}}
            <div class="rounded-xl border border-slate-800 bg-slate-900 shadow-sm overflow-hidden">
                <div class="p-2 border-b border-slate-800 bg-slate-950/40">
                    <x-segmented class="w-full">
                        <x-tab-button size="sm" class="flex-1" icon="users" :count="$total"
                            x-bind:class="activeTab === 'signers' ? '!bg-emerald-500/10 !text-emerald-700 dark:!text-emerald-400 !border-emerald-500/30' : ''"
                            x-on:click="activeTab = 'signers'">
                            Penandatangan
                        </x-tab-button>
                        <x-tab-button size="sm" class="flex-1" icon="history" :count="$document->auditLogs->count()"
                            x-bind:class="activeTab === 'audit' ? '!bg-emerald-500/10 !text-emerald-700 dark:!text-emerald-400 !border-emerald-500/30' : ''"
                            x-on:click="activeTab = 'audit'">
                            Riwayat
                        </x-tab-button>
                        <x-tab-button size="sm" class="flex-1" icon="shield-check"
                            x-bind:class="activeTab === 'integrity' ? '!bg-emerald-500/10 !text-emerald-700 dark:!text-emerald-400 !border-emerald-500/30' : ''"
                            x-on:click="activeTab = 'integrity'">
                            Integritas
                        </x-tab-button>
                    </x-segmented>
                </div>

                {{-- Tab 1: Penandatangan List --}}
                <div x-show="activeTab === 'signers'" class="divide-y divide-slate-800">
                    <ul class="divide-y divide-slate-800">
                        @foreach ($signers as $signer)
                            @php
                                $isActive = in_array($signer->id, $activeIds, true);
                                $canEdit = $status->isInProgress() && ! $signer->status->hasActed();
                            @endphp
                            <li wire:key="signer-{{ $signer->id }}" class="p-4 space-y-2.5">
                                <div class="flex items-start gap-3 min-w-0">
                                    <span class="relative w-8 h-8 rounded-full text-xs font-bold inline-flex items-center justify-center text-white no-dark-invert shrink-0 mt-0.5" style="background: {{ $signer->color_tag }}">
                                        {{ $signer->initials() }}
                                        @if ($document->isSequential())
                                            <span class="absolute -top-1 -right-1 w-4 h-4 rounded-full bg-slate-900 border border-slate-700 text-[9px] text-slate-200 inline-flex items-center justify-center">{{ $signer->signing_order }}</span>
                                        @endif
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="text-xs font-semibold text-slate-100 truncate">
                                            {{ $signer->name }}
                                            @if ($signer->is_owner)
                                                <span class="font-normal text-slate-400">(Anda)</span>
                                            @endif
                                        </p>
                                        <p class="text-[11px] text-slate-400 truncate">{{ $signer->contactLabel() }}</p>

                                        <div class="flex flex-wrap items-center gap-1 mt-1.5">
                                            <x-badge :color="$signer->status->color()">{{ strtoupper($signer->status->label()) }}</x-badge>
                                            @if ($isActive && ! $signer->status->hasActed())
                                                <x-badge color="amber">GILIRAN SEKARANG</x-badge>
                                            @elseif ($status->isInProgress() && ! $signer->status->hasActed())
                                                <x-badge color="slate">MENUNGGU GILIRAN</x-badge>
                                            @endif
                                            @if ($signer->hasPasscode())
                                                <x-badge color="slate"><i data-lucide="lock" class="w-3 h-3"></i> PASSCODE</x-badge>
                                            @endif
                                        </div>

                                        @if ($signer->signed_at)
                                            <p class="text-[11px] text-emerald-700 dark:text-emerald-400 mt-1">Ditandatangani {{ $signer->signed_at->isoFormat('D MMM Y, HH:mm') }} WIB</p>
                                        @elseif ($signer->viewed_at)
                                            <p class="text-[11px] text-slate-400 mt-1">Dibuka {{ $signer->viewed_at->isoFormat('D MMM Y, HH:mm') }} WIB</p>
                                        @endif
                                    </div>
                                </div>

                                @if ($canEdit)
                                    <div class="flex flex-wrap items-center gap-1.5 pt-1 border-t border-slate-800 justify-end">
                                        @if ($url = $signer->signingUrl())
                                            <x-copy-button :text="$url" label="Salin Link" />
                                        @endif
                                        @if ($isActive && $signer->email && ! $signer->is_owner)
                                            <x-secondary-button size="xs" type="button" wire:click="remind('{{ $signer->id }}')">
                                                <i data-lucide="bell-ring" class="w-3.5 h-3.5"></i> Ingatkan
                                            </x-secondary-button>
                                        @endif
                                        @unless ($signer->is_owner)
                                            <x-icon-button icon="user-pen" :label="'Ubah kontak '.$signer->name" tone="edit" wire:click="editContact('{{ $signer->id }}')" />
                                        @endunless
                                    </div>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Tab 2: Audit Trail --}}
                <div x-show="activeTab === 'audit'" x-cloak class="p-4">
                    <div class="flex items-center justify-between mb-3 pb-2 border-b border-slate-800">
                        <h3 class="text-xs font-bold text-slate-200">Riwayat (audit trail)</h3>
                        <span class="text-[10px] text-slate-400">{{ $document->auditLogs->count() }} aktivitas</span>
                    </div>
                    <ol class="space-y-4 max-h-[460px] overflow-y-auto custom-scrollbar pr-1">
                        @forelse ($document->auditLogs->reverse() as $log)
                            <li class="flex gap-3">
                                <span class="w-7 h-7 rounded-full bg-slate-800 border border-slate-700 inline-flex items-center justify-center shrink-0">
                                    <i data-lucide="{{ $log->event_type->icon() }}" class="w-3.5 h-3.5 text-slate-300"></i>
                                </span>
                                <div class="min-w-0 text-[11px] flex-1">
                                    <p class="text-xs font-semibold text-slate-200">{{ $log->event_type->label() }}</p>
                                    <p class="text-slate-400">
                                        {{ $log->signer?->name ?? $log->user?->name ?? 'Sistem' }}
                                        · {{ $log->created_at->isoFormat('D MMM Y, HH:mm:ss') }}
                                    </p>
                                    @if ($log->ip_address)
                                        <p class="text-slate-500 font-mono text-[10px] truncate" title="{{ $log->user_agent }}">{{ $log->ip_address }}</p>
                                    @endif
                                    @if (! empty($log->metadata['reason']))
                                        <p class="text-slate-300 mt-0.5">“{{ $log->metadata['reason'] }}”</p>
                                    @endif
                                </div>
                            </li>
                        @empty
                            <p class="text-xs text-slate-400 text-center py-6">Belum ada riwayat aktivitas.</p>
                        @endforelse
                    </ol>
                </div>

                {{-- Tab 3: Integritas Dokumen --}}
                <div x-show="activeTab === 'integrity'" x-cloak class="p-4 space-y-3.5 text-[11px]">
                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400 font-medium">SHA-256 Dokumen Awal</span>
                            <x-copy-button :text="$document->original_hash_sha256" label="Salin" />
                        </div>
                        <p class="font-mono text-[10px] text-slate-200 bg-slate-950/60 p-2 rounded-lg border border-slate-800 break-all select-all">{{ $document->original_hash_sha256 }}</p>
                    </div>

                    @if ($document->completed_hash_sha256)
                        <div class="space-y-1">
                            <div class="flex items-center justify-between">
                                <span class="text-slate-400 font-medium">SHA-256 Dokumen Final (Tersegel)</span>
                                <x-copy-button :text="$document->completed_hash_sha256" label="Salin" />
                            </div>
                            <p class="font-mono text-[10px] text-emerald-800 dark:text-emerald-300 font-medium bg-emerald-500/10 p-2 rounded-lg border border-emerald-500/30 break-all select-all">{{ $document->completed_hash_sha256 }}</p>
                        </div>
                    @endif

                    <div class="space-y-1">
                        <div class="flex items-center justify-between">
                            <span class="text-slate-400 font-medium">ID Dokumen (UUID)</span>
                            <x-copy-button :text="$document->id" label="Salin" />
                        </div>
                        <p class="font-mono text-[10px] text-slate-200 bg-slate-950/60 p-2 rounded-lg border border-slate-800 break-all select-all">{{ $document->id }}</p>
                    </div>

                    <div class="flex flex-wrap gap-2 pt-2 border-t border-slate-800">
                        <x-secondary-button size="xs" type="button" wire:click="download('original')">
                            <i data-lucide="file-down" class="w-3.5 h-3.5"></i> PDF Asli
                        </x-secondary-button>
                        @if ($status->isInProgress() && ! $document->isAwaitingSeal())
                            <x-secondary-button size="xs" tone="danger" type="button" x-on:click="$dispatch('open-modal', 'void-document')">
                                <i data-lucide="ban" class="w-3.5 h-3.5"></i> Batalkan Dokumen
                            </x-secondary-button>
                        @elseif (! $status->isInProgress())
                            <x-secondary-button size="xs" tone="danger" type="button" x-on:click="$wire.deleteConfirmation = ''; $dispatch('open-modal', 'delete-document')">
                                <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Hapus Dokumen
                            </x-secondary-button>
                        @endif
                    </div>
                </div>
            </div>
        </aside>
    </div>

    {{-- Modal: Bagikan Tautan --}}
    <x-modal name="distribution" max-width="2xl">
        <div class="p-5 sm:p-6 space-y-4 overflow-y-auto custom-scrollbar">
            <x-modal-header title="Bagikan tautan penandatanganan" icon="share-2" closeable>
                @if ($document->send_via_email)
                    Email undangan sudah dikirim ke penandatangan yang gilirannya aktif. Anda juga bisa membagikan tautannya langsung.
                @else
                    Bagikan tautan unik ini lewat WhatsApp, Telegram, atau chat. Setiap tautan hanya untuk satu orang.
                @endif
            </x-modal-header>

            <ul class="space-y-3">
                @foreach ($signers as $signer)
                    @continue($signer->status->hasActed() || ! $signer->signingUrl())
                    <li class="rounded-xl border border-slate-800 bg-slate-950/40 p-3 space-y-2" style="border-left: 4px solid {{ $signer->color_tag }}">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-sm font-semibold text-slate-100 truncate">{{ $signer->name }} @if ($signer->is_owner)<span class="font-normal text-slate-400">(Anda)</span>@endif</p>
                            @if (! in_array($signer->id, $activeIds, true))
                                <x-badge color="slate">AKTIF SETELAH GILIRAN SEBELUMNYA</x-badge>
                            @endif
                        </div>
                        <p class="font-mono text-[11px] text-slate-400 break-all select-all">{{ $signer->signingUrl() }}</p>
                        @if ($signer->hasPasscode())
                            <p class="text-[11px] text-amber-400">Dilindungi passcode. Kirim passcode lewat saluran terpisah, jangan di pesan yang sama.</p>
                        @endif
                        <div class="flex flex-wrap gap-1.5">
                            <x-copy-button :text="$signer->signingUrl()" label="Salin Link Unik" />
                            <x-copy-button :text="$signer->whatsappMessage()" label="Salin Format WhatsApp" icon="message-square-text" />
                            @if ($wa = $signer->whatsappUrl())
                                <a href="{{ $wa }}" target="_blank" rel="noopener" class="inline-flex items-center gap-2 min-h-[44px] sm:min-h-[30px] px-2.5 py-1 rounded-lg border border-emerald-500/30 bg-emerald-500/10 text-[11px] font-semibold text-emerald-400 hover:bg-emerald-500/20 transition">
                                    <i data-lucide="send" class="w-3.5 h-3.5"></i> Buka WhatsApp
                                </a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>

            <x-modal-actions>
                <x-primary-button type="button" x-on:click="$dispatch('close')">Selesai</x-primary-button>
            </x-modal-actions>
        </div>
    </x-modal>

    {{-- Modal: Ubah Kontak Penandatangan --}}
    <x-record-form-modal name="edit-contact" title="Ubah kontak penandatangan" subtitle="Tautan lama otomatis tidak berlaku setelah disimpan" icon="user-pen" max-width="lg" close-action="closeContact">
        <form wire:submit="saveContact" class="space-y-4">
            <div>
                <x-input-label for="contactName" value="Nama lengkap *" />
                <x-text-input id="contactName" wire:model="contactName" class="w-full" />
                <x-input-error :messages="$errors->get('contactName')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="contactEmail" value="Email" />
                <x-text-input id="contactEmail" type="email" wire:model="contactEmail" class="w-full" />
                <x-input-error :messages="$errors->get('contactEmail')" class="mt-1" />
            </div>
            <div>
                <x-input-label for="contactPhone" value="Nomor WhatsApp" />
                <x-text-input id="contactPhone" type="tel" wire:model="contactPhone" class="w-full font-mono" />
                <x-input-error :messages="$errors->get('contactPhone')" class="mt-1" />
            </div>
            <x-modal-actions>
                <x-secondary-button type="button" wire:click="closeContact">Batal</x-secondary-button>
                <x-primary-button type="submit" wire:loading.attr="disabled">
                    <x-loading-label target="saveContact" loading="Menyimpan...">Simpan & Kirim Ulang Tautan</x-loading-label>
                </x-primary-button>
            </x-modal-actions>
        </form>
    </x-record-form-modal>

    {{-- Modal: Batalkan Dokumen --}}
    <x-modal name="void-document" max-width="md">
        <form wire:submit="void" class="p-5 sm:p-6 space-y-4">
            <x-modal-header title="Batalkan dokumen ini?" icon="ban" tone="rose">Semua tautan penandatangan langsung nonaktif. Penandatangan yang sedang membuka dokumen akan melihat pemberitahuan pembatalan. Tindakan ini tidak bisa diurungkan.</x-modal-header>
            <div>
                <x-input-label for="voidReason" value="Alasan (opsional, tercatat di audit trail)" />
                <x-textarea id="voidReason" wire:model="voidReason" rows="2" />
            </div>
            <x-modal-actions>
                <x-secondary-button type="button" x-on:click="$dispatch('close')">Kembali</x-secondary-button>
                <x-danger-button type="submit">Batalkan Dokumen</x-danger-button>
            </x-modal-actions>
        </form>
    </x-modal>

    <x-modal name="delete-document" max-width="sm">
        <form wire:submit="deleteDocument" class="p-5 sm:p-6 space-y-4">
            <x-modal-header title="Hapus dokumen ini?" icon="trash-2" tone="rose">File PDF, data penandatangan, dan audit trail ikut terhapus permanen. Tautan dan QR verifikasi dokumen ini tidak akan bisa dibuka lagi.</x-modal-header>
            <div>
                <x-input-label for="deleteConfirmation" value="Ketik HAPUS untuk konfirmasi" />
                <x-text-input id="deleteConfirmation" wire:model="deleteConfirmation" class="block mt-1 w-full font-mono" autocomplete="off" autocapitalize="characters" spellcheck="false" />
                <x-input-error :messages="$errors->get('deleteConfirmation')" class="mt-2" />
            </div>
            <x-modal-actions>
                <x-secondary-button type="button" x-on:click="$dispatch('close')">Kembali</x-secondary-button>
                <x-danger-button type="submit" x-bind:disabled="$wire.deleteConfirmation !== 'HAPUS'" wire:loading.attr="disabled" class="disabled:opacity-50 disabled:cursor-not-allowed">
                    <x-loading-label target="deleteDocument" loading="Menghapus...">Hapus Dokumen</x-loading-label>
                </x-danger-button>
            </x-modal-actions>
        </form>
    </x-modal>

    {{-- Modal: Simpan sebagai Template --}}
    <x-record-form-modal name="save-template-modal" title="Simpan sebagai Template" subtitle="Simpan posisi tanda tangan dan tata letak dokumen ini sebagai template reusable." icon="copy" max-width="md" close-action="closeSaveTemplate">
        <form wire:submit="saveAsTemplate" class="space-y-4">
            <div>
                <x-input-label for="templateName" value="Nama Template *" />
                <x-text-input id="templateName" wire:model="templateName" class="w-full" maxlength="150" />
                <x-input-error :messages="$errors->get('templateName')" class="mt-1" />
            </div>
            <p class="text-xs text-slate-400">PDF, halaman, dan penempatan tanda tangan akan diduplikasi menjadi template tanpa mempengaruhi dokumen aktif saat ini.</p>
            <x-modal-actions>
                <x-secondary-button type="button" x-on:click="$dispatch('close')">Batal</x-secondary-button>
                <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="saveAsTemplate">
                    <x-loading-label target="saveAsTemplate" loading="Menyimpan...">Simpan Template</x-loading-label>
                </x-primary-button>
            </x-modal-actions>
        </form>
    </x-record-form-modal>
</div>
