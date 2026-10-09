@php
    $steps = [1 => ['Penandatangan', 'users'], 2 => ['Tata letak kotak', 'layout-template'], 3 => ['Kirim', 'send']];
@endphp

<div class="space-y-4 sm:space-y-6">
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <div class="min-w-0">
            <a href="{{ route('documents.index') }}" wire:navigate class="inline-flex items-center gap-1 text-xs text-slate-400 hover:text-slate-200 min-h-[44px] sm:min-h-0">
                <i data-lucide="arrow-left" class="w-3.5 h-3.5"></i> Semua dokumen
            </a>
            <h1 class="text-lg sm:text-xl font-bold text-slate-100 truncate">{{ $document->title }}</h1>
            <p class="text-xs text-slate-400">{{ $document->original_filename }} · {{ $document->total_pages }} halaman · <x-document-status-badge :status="$document->status" /></p>
        </div>

        <nav aria-label="Langkah persiapan" class="shrink-0">
            <ol class="flex items-center gap-1 p-1 rounded-lg bg-slate-950 border border-slate-800">
                @foreach ($steps as $number => [$label, $icon])
                    <li>
                        <button type="button" wire:click="goToStep({{ $number }})"
                            @if ($step === $number) aria-current="step" @endif
                            @class([
                                'inline-flex items-center gap-2 min-h-[44px] sm:min-h-[36px] px-3 rounded-md text-xs font-semibold transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40',
                                'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400' => $step === $number,
                                'text-slate-400 hover:text-slate-200' => $step !== $number,
                            ])>
                            <span @class(['w-5 h-5 rounded-full inline-flex items-center justify-center text-[10px] font-bold', 'bg-emerald-500 text-slate-950' => $step >= $number, 'bg-slate-800 text-slate-400' => $step < $number])>{{ $number }}</span>
                            <span class="hidden sm:inline">{{ $label }}</span>
                        </button>
                    </li>
                @endforeach
            </ol>
        </nav>
    </div>

    @if ($step === 1)
        <form wire:submit="saveSigners" class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_340px] gap-4 sm:gap-6 items-start">
            <section class="space-y-4">
                <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4 sm:p-5 shadow-sm dark:shadow-xl space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-bold text-slate-100">Siapa saja yang menandatangani?</h2>
                            <p class="text-xs text-slate-400">Setiap penandatangan mendapat warna dan tautan unik sendiri.</p>
                        </div>
                        <x-checkbox wire:model.live="ownerSigns" label="Saya perlu menandatangani dokumen ini" />
                    </div>

                    <x-input-error :messages="$errors->get('signers')" />

                    <ol class="space-y-3">
                        @foreach ($signers as $index => $signer)
                            @php
                                $color = $signer['is_owner'] ? config('paraf.owner_color') : config('paraf.signer_colors')[collect($signers)->take($index)->where('is_owner', false)->count() % count(config('paraf.signer_colors'))];
                            @endphp
                            <li wire:key="signer-row-{{ $signer['id'] ?? 'new-'.$index }}" class="rounded-xl border border-slate-800 bg-slate-950/40 p-3 sm:p-4" style="border-left: 4px solid {{ $color }}">
                                <div class="flex items-center justify-between gap-2 mb-3">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="w-6 h-6 rounded-full text-[11px] font-bold inline-flex items-center justify-center text-white no-dark-invert shrink-0" style="background: {{ $color }}">{{ $index + 1 }}</span>
                                        <span class="text-xs font-semibold text-slate-200 truncate">
                                            {{ $signer['is_owner'] ? 'Anda (pemilik dokumen)' : 'Penandatangan '.($index + 1) }}
                                        </span>
                                        @if ($signing_order_mode === 'SEQUENTIAL')
                                            <x-badge color="sky">URUTAN {{ $index + 1 }}</x-badge>
                                        @endif
                                    </div>
                                    <div class="flex items-center">
                                        <x-icon-button icon="arrow-up" label="Naikkan urutan" wire:click="moveSigner({{ $index }}, -1)" :disabled="$index === 0" />
                                        <x-icon-button icon="arrow-down" label="Turunkan urutan" wire:click="moveSigner({{ $index }}, 1)" :disabled="$index === count($signers) - 1" />
                                        <x-icon-button icon="trash" label="Hapus penandatangan" tone="danger" wire:click="removeSigner({{ $index }})" />
                                    </div>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <div>
                                        <x-input-label for="signer-name-{{ $index }}" value="Nama lengkap *" />
                                        <x-text-input id="signer-name-{{ $index }}" wire:model="signers.{{ $index }}.name" class="w-full" placeholder="Nama sesuai identitas" :disabled="$signer['is_owner']" />
                                        <x-input-error :messages="$errors->get('signers.'.$index.'.name')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label for="signer-email-{{ $index }}" value="Email" />
                                        <x-text-input id="signer-email-{{ $index }}" type="email" wire:model="signers.{{ $index }}.email" class="w-full" placeholder="Wajib untuk kirim otomatis" :disabled="$signer['is_owner']" />
                                        <x-input-error :messages="$errors->get('signers.'.$index.'.email')" class="mt-1" />
                                    </div>
                                    <div>
                                        <x-input-label for="signer-phone-{{ $index }}" value="Nomor WhatsApp" />
                                        <x-text-input id="signer-phone-{{ $index }}" type="tel" wire:model="signers.{{ $index }}.phone" class="w-full font-mono" placeholder="08xxxxxxxxxx (opsional)" />
                                        <x-input-error :messages="$errors->get('signers.'.$index.'.phone')" class="mt-1" />
                                    </div>
                                    @unless ($signer['is_owner'])
                                        <div>
                                            <x-input-label for="signer-passcode-{{ $index }}" value="Passcode akses (6 digit)" />
                                            @if ($signer['has_passcode'] && $signer['passcode'] === '')
                                                <div class="flex items-center gap-2 min-h-[44px] sm:min-h-[38px]">
                                                    <x-badge color="emerald"><i data-lucide="lock" class="w-3 h-3"></i> AKTIF</x-badge>
                                                    <x-text-button type="button" wire:click="clearPasscode({{ $index }})">Hapus passcode</x-text-button>
                                                </div>
                                            @else
                                                <x-text-input id="signer-passcode-{{ $index }}" wire:model="signers.{{ $index }}.passcode" inputmode="numeric" maxlength="6" autocomplete="off" class="w-full font-mono tracking-[0.3em]" placeholder="Opsional" />
                                            @endif
                                            <x-input-error :messages="$errors->get('signers.'.$index.'.passcode')" class="mt-1" />
                                        </div>
                                    @endunless
                                </div>
                            </li>
                        @endforeach
                    </ol>

                    <x-secondary-button type="button" wire:click="addSigner" class="w-full sm:w-auto">
                        <i data-lucide="user-plus" class="w-4 h-4"></i> Tambah Penandatangan
                    </x-secondary-button>
                </div>
            </section>

            <aside class="space-y-4">
                <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-4 space-y-3">
                    <h2 class="text-sm font-bold text-slate-100">Alur tanda tangan</h2>
                    <div class="grid grid-cols-1 gap-2">
                        @foreach (\App\Enums\SigningOrderMode::cases() as $mode)
                            <label @class(['flex items-start gap-3 p-3 rounded-lg border cursor-pointer min-h-[44px] transition', 'border-emerald-500/40 bg-emerald-500/5' => $signing_order_mode === $mode->value, 'border-slate-800 hover:border-slate-700' => $signing_order_mode !== $mode->value])>
                                <input type="radio" wire:model.live="signing_order_mode" value="{{ $mode->value }}" class="mt-0.5 text-emerald-500 bg-slate-950 border-slate-700 focus:ring-emerald-500">
                                <span>
                                    <span class="block text-xs font-semibold text-slate-200">{{ $mode->label() }}</span>
                                    <span class="block text-[11px] text-slate-400">
                                        {{ $mode === \App\Enums\SigningOrderMode::Parallel ? 'Semua penandatangan bisa tanda tangan bersamaan.' : 'Tautan berikutnya aktif setelah penandatangan sebelumnya selesai, sesuai nomor urut.' }}
                                    </span>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-xl border border-slate-800 bg-slate-900/80 p-4 space-y-3">
                    <h2 class="text-sm font-bold text-slate-100">Detail dokumen</h2>
                    <div>
                        <x-input-label for="title" value="Judul *" />
                        <x-text-input id="title" wire:model="title" class="w-full" maxlength="150" />
                        <x-input-error :messages="$errors->get('title')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="description" value="Pesan untuk penandatangan" />
                        <x-textarea id="description" wire:model="description" rows="3" />
                        <x-input-error :messages="$errors->get('description')" class="mt-1" />
                    </div>
                    <div>
                        <x-input-label for="expiry_days" value="Batas waktu (hari) *" />
                        <x-text-input id="expiry_days" type="number" wire:model="expiry_days" min="1" max="90" class="w-full font-mono" />
                        <x-input-error :messages="$errors->get('expiry_days')" class="mt-1" />
                    </div>
                </div>

                <div class="flex flex-col gap-2">
                    <x-primary-button type="submit" wire:loading.attr="disabled" class="w-full">
                        <x-loading-label target="saveSigners" loading="Menyimpan...">Simpan & Atur Kotak Tanda Tangan</x-loading-label>
                    </x-primary-button>
                    <x-text-button type="button" class="self-center text-rose-400" x-on:click="$dispatch('open-modal', 'delete-draft')">Hapus draft ini</x-text-button>
                </div>
            </aside>
        </form>
    @elseif ($step === 2)
        <div wire:key="editor-{{ md5(json_encode($editorConfig['signers'])) }}" wire:ignore
             x-data="documentEditor(@js($editorConfig))" x-on:keydown.window="onKeydown($event)"
             class="grid grid-cols-1 lg:grid-cols-[270px_minmax(0,1fr)] gap-4 items-start">

            <aside class="lg:sticky lg:top-4 space-y-3 order-1">
                {{-- 1. Pilih penandatangan --}}
                <div class="rounded-xl border border-slate-800 bg-slate-900 p-3 space-y-2">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">1. Pilih penandatangan</p>
                    <div class="flex lg:flex-col gap-2 overflow-x-auto custom-scrollbar pb-1 lg:pb-0">
                        <template x-for="signer in signers" :key="signer.id">
                            <button type="button" x-on:click="activeSignerId = signer.id"
                                :aria-pressed="activeSignerId === signer.id"
                                class="flex items-center gap-2 min-h-[44px] px-2.5 py-2 rounded-lg border text-left transition shrink-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40 cursor-pointer"
                                :class="activeSignerId === signer.id ? 'border-slate-600 bg-slate-800' : 'border-slate-800 hover:border-slate-700 bg-slate-950/40'">
                                <span class="w-3.5 h-3.5 rounded-full shrink-0" :style="{ background: signer.color }"></span>
                                <span class="min-w-0 flex-1">
                                    <span class="block text-xs font-semibold text-slate-200 truncate" x-text="signer.is_owner ? signer.name + ' (Anda)' : signer.name"></span>
                                    <span class="block text-[10px]" :class="countFor(signer.id, ['SIGNATURE','INITIAL']) ? 'text-slate-400' : 'text-amber-700 dark:text-amber-400 font-medium'"
                                          x-text="countFor(signer.id) + ' kotak' + (countFor(signer.id, ['SIGNATURE','INITIAL']) ? '' : ' · perlu tanda tangan')"></span>
                                </span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- 2. Seret ke halaman --}}
                <div class="rounded-xl border border-slate-800 bg-slate-900 p-3 space-y-2">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">2. Seret ke halaman</p>
                    <p class="text-[11px] text-slate-400 lg:hidden">Ketuk jenis kotak, lalu ketuk posisi di halaman.</p>
                    <div class="grid grid-cols-3 lg:grid-cols-2 gap-2">
                        <template x-for="(spec, type) in types" :key="type">
                            <button type="button" draggable="true"
                                x-on:dragstart="onPaletteDragStart($event, type)"
                                x-on:click="startPlacing(type)"
                                :aria-pressed="placingType === type"
                                class="flex flex-col items-center justify-center gap-1.5 min-h-[50px] p-2 rounded-lg border text-[11px] font-medium text-slate-200 cursor-grab active:cursor-grabbing transition focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/40"
                                :class="placingType === type ? 'border-emerald-500/50 bg-emerald-500/10' : 'border-slate-800 hover:border-slate-700 bg-slate-950/40'">
                                <i :data-lucide="spec.icon" class="w-4 h-4" :style="{ color: signer(activeSignerId).color }"></i>
                                <span x-text="spec.label" class="text-center text-[11px] leading-tight"></span>
                            </button>
                        </template>
                    </div>
                </div>

                {{-- 3. Pintasan Otomatisasi --}}
                <div class="rounded-xl border border-slate-800 bg-slate-900 p-3 space-y-2">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">3. Alat Cepat</p>
                    <button type="button" x-on:click="applyInitialToAllPages()"
                        class="w-full inline-flex items-center gap-2 px-3 py-2 rounded-lg border border-slate-800 bg-slate-950/40 hover:border-emerald-500/40 hover:text-emerald-700 dark:hover:text-emerald-400 text-xs font-medium text-slate-200 transition cursor-pointer"
                        title="Terapkan kotak paraf di sudut kanan bawah setiap halaman">
                        <i data-lucide="copy-plus" class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0"></i>
                        <span class="text-left leading-tight">
                            Paraf di Semua Halaman
                            <span class="block text-[10px] text-slate-400 font-normal">Terapkan ke penandatangan aktif</span>
                        </span>
                    </button>
                </div>

                {{-- Multi-Selection Alignment Toolbar --}}
                <div x-show="selectedIds.length > 1" x-cloak class="rounded-xl border border-slate-800 bg-slate-900 p-3 space-y-3">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-400"></span>
                            <p class="text-[11px] font-bold uppercase tracking-wider text-slate-300" x-text="selectedIds.length + ' kotak terpilih'"></p>
                        </div>
                        <button type="button" x-on:click="removeSelected()" class="text-[10px] text-rose-400 hover:text-rose-300 font-semibold cursor-pointer">
                            Hapus Semua
                        </button>
                    </div>

                    <div>
                        <p class="text-[11px] text-slate-400 mb-1.5">Perataan Posisi:</p>
                        <div class="grid grid-cols-4 gap-1.5">
                            <button type="button" x-on:click="alignLeft()" title="Rata Kiri" class="p-2 rounded-lg border border-slate-800 bg-slate-950/40 hover:bg-slate-800 flex items-center justify-center text-slate-300 transition cursor-pointer">
                                <i data-lucide="align-left" class="w-4 h-4"></i>
                            </button>
                            <button type="button" x-on:click="alignRight()" title="Rata Kanan" class="p-2 rounded-lg border border-slate-800 bg-slate-950/40 hover:bg-slate-800 flex items-center justify-center text-slate-300 transition cursor-pointer">
                                <i data-lucide="align-right" class="w-4 h-4"></i>
                            </button>
                            <button type="button" x-on:click="alignTop()" title="Rata Atas" class="p-2 rounded-lg border border-slate-800 bg-slate-950/40 hover:bg-slate-800 flex items-center justify-center text-slate-300 transition cursor-pointer">
                                <i data-lucide="align-vertical-space-around" class="w-4 h-4"></i>
                            </button>
                            <button type="button" x-on:click="alignBottom()" title="Rata Bawah" class="p-2 rounded-lg border border-slate-800 bg-slate-950/40 hover:bg-slate-800 flex items-center justify-center text-slate-300 transition cursor-pointer">
                                <i data-lucide="align-end-horizontal" class="w-4 h-4"></i>
                            </button>
                        </div>
                    </div>

                    <div>
                        <p class="text-[11px] text-slate-400 mb-1.5">Samakan Ukuran & Jarak:</p>
                        <div class="grid grid-cols-3 gap-1.5">
                            <button type="button" x-on:click="matchWidth()" title="Samakan Lebar" class="p-1.5 rounded-lg border border-slate-800 bg-slate-950/40 hover:bg-slate-800 flex flex-col items-center justify-center text-[10px] text-slate-300 transition cursor-pointer">
                                <i data-lucide="scaling" class="w-3.5 h-3.5 mb-0.5"></i> Lebar
                            </button>
                            <button type="button" x-on:click="matchHeight()" title="Samakan Tinggi" class="p-1.5 rounded-lg border border-slate-800 bg-slate-950/40 hover:bg-slate-800 flex flex-col items-center justify-center text-[10px] text-slate-300 transition cursor-pointer">
                                <i data-lucide="rows-3" class="w-3.5 h-3.5 mb-0.5"></i> Tinggi
                            </button>
                            <button type="button" x-on:click="distributeVertical()" title="Bagi Jarak Rata Vertikal" class="p-1.5 rounded-lg border border-slate-800 bg-slate-950/40 hover:bg-slate-800 flex flex-col items-center justify-center text-[10px] text-slate-300 transition cursor-pointer disabled:opacity-40" :disabled="selectedIds.length < 3">
                                <i data-lucide="distribute-vertical" class="w-3.5 h-3.5 mb-0.5"></i> Jarak
                            </button>
                        </div>
                    </div>

                    <div class="pt-1">
                        <label class="block font-medium text-[11px] text-slate-400 mb-1.5">Ubah Penandatangan Serentak:</label>
                        <div class="flex flex-wrap gap-1.5">
                            <template x-for="signer in signers" :key="signer.id">
                                <button type="button" x-on:click="assignSignerToSelected(signer.id)"
                                    class="inline-flex items-center gap-1.5 px-2 py-1 rounded-md border text-[11px] cursor-pointer hover:border-slate-600 bg-slate-950/40 text-slate-300 border-slate-800">
                                    <span class="w-2.5 h-2.5 rounded-full" :style="{ background: signer.color }"></span>
                                    <span x-text="signer.name"></span>
                                </button>
                            </template>
                        </div>
                    </div>
                </div>

                {{-- Kotak Terpilih (Inspector) --}}
                <div x-show="selected && selectedIds.length <= 1" x-cloak class="rounded-xl border border-slate-800 bg-slate-900 p-3 space-y-3">
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Kotak terpilih</p>
                        <div class="flex items-center gap-1">
                            <x-icon-button icon="copy" label="Duplikat kotak (Ctrl+D)" x-on:click="duplicateSelected()" />
                            <x-icon-button icon="trash" label="Hapus kotak (Delete)" tone="danger" x-on:click="removeSelected()" />
                        </div>
                    </div>
                    <template x-if="selected">
                        <div class="space-y-3">
                            <p class="text-xs text-slate-200 font-medium" x-text="types[selected.type].label + ' · halaman ' + selected.page"></p>
                            <div>
                                <label for="inspector-signer" class="block font-medium text-xs text-slate-300 mb-1.5">Milik</label>
                                <div class="flex flex-wrap gap-1.5" id="inspector-signer">
                                    <template x-for="signer in signers" :key="signer.id">
                                        <button type="button" x-on:click="updateSelected({ signer_id: signer.id }); activeSignerId = signer.id"
                                            class="inline-flex items-center gap-1.5 min-h-[32px] px-2 rounded-md border text-[11px] cursor-pointer"
                                            :class="selected.signer_id === signer.id ? 'border-slate-500 bg-slate-800 text-slate-100' : 'border-slate-800 text-slate-400'">
                                            <span class="w-2.5 h-2.5 rounded-full" :style="{ background: signer.color }"></span>
                                            <span x-text="signer.name"></span>
                                        </button>
                                    </template>
                                </div>
                            </div>
                            <template x-if="selected.type === 'TEXT' || selected.type === 'CHECKBOX'">
                                <div>
                                    <label for="inspector-label" class="block font-medium text-xs text-slate-300 mb-1.5" x-text="selected.type === 'TEXT' ? 'Petunjuk isian' : 'Teks persetujuan'"></label>
                                    <input id="inspector-label" type="text" maxlength="100" :value="selected.label" x-on:input="updateSelected({ label: $event.target.value })"
                                        class="w-full min-h-[44px] sm:min-h-0 bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500">
                                </div>
                            </template>
                            <template x-if="selected.type === 'TEXT' || selected.type === 'CHECKBOX'">
                                <label class="flex items-center gap-2 min-h-[44px] sm:min-h-0 text-xs text-slate-300 cursor-pointer">
                                    <input type="checkbox" :checked="selected.required" x-on:change="updateSelected({ required: $event.target.checked })" class="w-4 h-4 rounded border-slate-700 bg-slate-950 text-emerald-500 focus:ring-emerald-500">
                                    Wajib diisi
                                </label>
                            </template>
                            <p class="text-[11px] text-slate-400 leading-relaxed">Geser untuk memindah, tarik sudut kanan bawah untuk ukuran. Panah keyboard geser 1px (Shift: 10px). Delete menghapus.</p>
                        </div>
                    </template>
                </div>
            </aside>

            <section class="order-2 min-w-0 space-y-3">
                {{-- Sticky Toolbar --}}
                <div class="sticky top-0 z-20 flex flex-wrap items-center justify-between gap-2 rounded-xl border border-slate-800 bg-slate-900/95 backdrop-blur px-3 py-2">
                    <div class="flex items-center gap-2">
                        <span class="w-2 h-2 rounded-full shrink-0" :class="dirty ? 'bg-amber-400' : 'bg-emerald-400'"></span>
                        <p class="text-xs" :class="saveError ? 'text-rose-400' : 'text-slate-400'" x-text="savedLabel()" aria-live="polite"></p>
                    </div>

                    {{-- Workspace Tools: Pager + Zoom + Snapping + Fullscreen --}}
                    <div class="flex flex-wrap items-center gap-1.5">
                        {{-- Page Jumper --}}
                        <div class="inline-flex items-center gap-1 px-1 py-0.5 rounded-lg border border-slate-800 bg-slate-950/40 text-xs">
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

                        {{-- Zoom --}}
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

                        {{-- Snapping Magnet --}}
                        <button type="button" x-on:click="toggleSnapping()"
                            :class="snappingEnabled ? 'text-emerald-700 dark:text-emerald-400 bg-emerald-500/10 border-emerald-500/30' : 'text-slate-500 border-slate-800 hover:text-slate-200'"
                            class="w-7 h-7 rounded-lg border flex items-center justify-center transition cursor-pointer"
                            :title="snappingEnabled ? 'Panduan magnetis aktif' : 'Panduan magnetis nonaktif'">
                            <i data-lucide="magnet" class="w-3.5 h-3.5"></i>
                        </button>

                        {{-- Fullscreen Toggle --}}
                        <button type="button" x-on:click="toggleFullscreen()"
                            class="w-7 h-7 rounded-lg border border-slate-800 bg-slate-950/40 flex items-center justify-center text-slate-400 hover:text-slate-200 hover:bg-slate-800 transition cursor-pointer"
                            title="Layar penuh">
                            <i :data-lucide="isFullscreen ? 'minimize-2' : 'maximize-2'" class="w-3.5 h-3.5"></i>
                        </button>
                    </div>

                    {{-- Actions --}}
                    <div class="flex items-center gap-2">
                        <x-secondary-button size="sm" type="button" x-on:click="if (await save()) $wire.openSaveTemplate()" x-bind:disabled="saving" title="Simpan tata letak ini sebagai template">
                            <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                            <span class="hidden sm:inline">Simpan Template</span>
                        </x-secondary-button>
                        <x-secondary-button size="sm" type="button" wire:click="goToStep(1)">Kembali</x-secondary-button>
                        <x-primary-button size="sm" type="button" x-on:click="saveAndContinue()" x-bind:disabled="saving || signersMissingSignature().length > 0"
                            x-bind:title="signersMissingSignature().length ? 'Setiap penandatangan perlu minimal satu kotak tanda tangan atau paraf' : ''">
                            Lanjut ke Pengiriman
                        </x-primary-button>
                    </div>
                </div>

                <p x-show="placingType" x-cloak class="text-xs text-emerald-700 dark:text-emerald-400 font-medium" role="status">Ketuk posisi di halaman untuk menaruh kotak <span x-text="placingType && types[placingType].label.toLowerCase()"></span>.</p>

                <div x-show="loading" class="rounded-xl border border-slate-800 bg-slate-900 p-10 text-center text-xs text-slate-400">
                    <x-spinner class="w-5 h-5 mx-auto mb-2 text-emerald-500" /> Memuat PDF…
                </div>
                <div x-show="loadError" x-cloak class="rounded-xl border border-rose-500/40 bg-rose-500/10 p-4 text-xs text-rose-900 dark:text-rose-300 font-medium" x-text="loadError" role="alert"></div>

                {{-- Pages Canvas Container --}}
                <div x-show="!loading && !loadError" class="space-y-4 sm:space-y-6 mx-auto max-w-[900px] flex flex-col items-center">
                    <template x-for="page in pages" :key="page.number">
                        <div class="w-full flex flex-col items-center">
                            <div class="w-full flex items-center justify-between text-[11px] text-slate-400 mb-1 px-1"
                                 :style="{ maxWidth: zoomLevel === 100 ? '900px' : 'none', width: zoomLevel + '%' }">
                                <span class="font-medium text-slate-300" x-text="'Halaman ' + page.number + ' dari ' + pages.length"></span>
                                <div class="flex items-center gap-3">
                                    <button type="button" x-show="fieldsOn(page.number).length > 0" x-on:click="clearPage(page.number)"
                                        class="text-[10px] text-rose-400 hover:text-rose-300 transition cursor-pointer">
                                        Hapus kotak halaman ini
                                    </button>
                                    <span class="font-mono text-[10px] text-slate-400" x-text="Math.round(page.width) + ' × ' + Math.round(page.height) + ' pt'"></span>
                                </div>
                            </div>
                            <div :data-page="page.number" :style="pageStyle(page)"
                                class="relative w-full bg-white rounded-md shadow-lg border border-slate-800/80 overflow-hidden select-none"
                                :class="placingType ? 'cursor-crosshair' : ''"
                                x-on:dragover.prevent x-on:drop.prevent="onPageDrop($event, page)"
                                x-on:click="onPageClick($event, page)">
                                <div data-canvas-slot class="absolute inset-0"></div>

                                <template x-for="guide in guides.filter(g => g.page === page.number)">
                                    <div class="absolute bg-fuchsia-500 pointer-events-none z-20" :style="guideStyle(guide)"></div>
                                </template>

                                <template x-for="field in fieldsOn(page.number)" :key="field.id">
                                    <div class="absolute z-10 rounded-[3px] border-2 flex items-center justify-center text-[10px] sm:text-[11px] font-semibold cursor-move touch-none"
                                        :class="isSelected(field.id) ? 'ring-2 ring-offset-1 ring-slate-900/90 shadow-md font-bold' : ''"
                                        :style="fieldStyle(field)"
                                        x-on:pointerdown="startDrag($event, field, 'move')"
                                        x-on:click.stop="selectField(field, $event)">
                                        <span class="truncate px-1 pointer-events-none" x-text="field.type === 'CHECKBOX' ? '' : (field.label || types[field.type].label) + ' · ' + signer(field.signer_id).name"></span>
                                        <span x-show="field.type === 'CHECKBOX'" class="pointer-events-none">✓</span>
                                        <span class="absolute -right-1.5 -bottom-1.5 w-4 h-4 rounded-sm border-2 border-white cursor-se-resize touch-none"
                                            :style="{ background: signer(field.signer_id).color }"
                                            x-on:pointerdown="startDrag($event, field, 'resize')" aria-hidden="true"></span>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
            </section>
        </div>
    @else
        <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_340px] gap-4 sm:gap-6 items-start">
            <section class="rounded-2xl border border-slate-800 bg-slate-900 p-4 sm:p-6 shadow-sm dark:shadow-xl space-y-5">
                <div>
                    <h2 class="text-base font-bold text-slate-100">Siap dikirim ke {{ $document->signers->count() }} penandatangan</h2>
                    <p class="text-xs text-slate-400 mt-1">
                        Alur {{ strtolower($document->signing_order_mode->label()) }} · berlaku {{ $document->expiry_days }} hari sejak dikirim ({{ now()->addDays($document->expiry_days)->isoFormat('D MMMM Y') }}).
                    </p>
                </div>

                <ol class="divide-y divide-slate-800 rounded-xl border border-slate-800">
                    @foreach ($document->signers as $signer)
                        <li class="flex items-center gap-3 p-3">
                            <span class="w-8 h-8 rounded-full text-[11px] font-bold inline-flex items-center justify-center text-white no-dark-invert shrink-0" style="background: {{ $signer->color_tag }}">{{ $signer->initials() }}</span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-semibold text-slate-100 truncate">{{ $signer->name }} @if ($signer->is_owner)<span class="text-slate-400 font-normal">(Anda)</span>@endif</p>
                                <p class="text-[11px] text-slate-400 truncate">{{ $signer->contactLabel() }} · {{ $signer->fields->count() }} kotak @if ($signer->hasPasscode()) · passcode aktif @endif</p>
                            </div>
                            @if ($document->isSequential())
                                <x-badge color="sky">URUTAN {{ $signer->signing_order }}</x-badge>
                            @endif
                        </li>
                    @endforeach
                </ol>

                <div class="space-y-2">
                    <h3 class="text-sm font-bold text-slate-100">Cara mengirim</h3>
                    <x-checkbox-card wire:model.live="sendViaEmail" label="Kirim email undangan otomatis" description="Setiap penandatangan menerima email berisi tombol Tinjau & Tanda Tangani. Matikan bila Anda hanya ingin membagikan tautan lewat WhatsApp/chat." />
                    <p class="text-[11px] text-slate-400">Setelah dikirim, tautan unik tiap penandatangan selalu bisa disalin dari halaman detail dokumen, termasuk format pesan WhatsApp.</p>
                </div>

                <x-input-error :messages="$errors->get('document')" />

                <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5">
                    <x-secondary-button type="button" wire:click="goToStep(2)">Ubah Tata Letak</x-secondary-button>
                    @if ($document->isTemplate())
                        <x-primary-button type="button" x-on:click="Livewire.navigate('{{ route('documents.index', ['status' => 'template']) }}')">
                            <i data-lucide="check" class="w-4 h-4"></i>
                            Selesai Mengedit Template
                        </x-primary-button>
                    @else
                        <x-primary-button type="button" wire:click="send" wire:loading.attr="disabled">
                            <i data-lucide="send" class="w-4 h-4"></i>
                            <x-loading-label target="send" loading="Mengirim...">Kirim Dokumen</x-loading-label>
                        </x-primary-button>
                    @endif
                </div>
            </section>

            <aside class="rounded-xl border border-slate-800 bg-slate-900/80 p-4 space-y-2 text-xs text-slate-400">
                <h3 class="text-sm font-bold text-slate-100">Setelah dikirim</h3>
                <ul class="space-y-2 list-disc ps-4">
                    <li>Tata letak kotak dan daftar penandatangan dikunci.</li>
                    <li>Kontak penandatangan yang belum tanda tangan masih bisa dikoreksi; tautannya akan diganti.</li>
                    <li>Begitu semua selesai, PDF final disegel bersama lembar audit trail dan dikirim ke semua pihak.</li>
                </ul>
            </aside>
        </div>
    @endif

    <x-modal name="delete-draft" max-width="md">
        <div class="p-5 sm:p-6 space-y-4">
            <x-modal-header title="Hapus draft ini?" icon="trash" tone="rose">PDF dan semua pengaturan penandatangan akan dihapus permanen.</x-modal-header>
            <x-modal-actions>
                <x-secondary-button type="button" x-on:click="$dispatch('close')">Batal</x-secondary-button>
                <x-danger-button type="button" wire:click="deleteDraft">Hapus Draft</x-danger-button>
            </x-modal-actions>
        </div>
    </x-modal>

    {{-- Modal: Simpan sebagai Template --}}
    <x-record-form-modal name="save-template-modal" title="Simpan sebagai Template" subtitle="Simpan posisi tanda tangan dan tata letak dokumen ini sebagai template reusable." icon="copy" max-width="md" close-action="closeSaveTemplate">
        <form wire:submit="saveAsTemplate" class="space-y-4">
            <div>
                <x-input-label for="prepareTemplateName" value="Nama Template *" />
                <x-text-input id="prepareTemplateName" wire:model="templateName" class="w-full" maxlength="150" />
                <x-input-error :messages="$errors->get('templateName')" class="mt-1" />
            </div>
            <p class="text-xs text-slate-400">PDF, halaman, dan penempatan tanda tangan akan diduplikasi menjadi template tanpa mempengaruhi draft aktif saat ini.</p>
            <x-modal-actions>
                <x-secondary-button type="button" x-on:click="$dispatch('close')">Batal</x-secondary-button>
                <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="saveAsTemplate">
                    <x-loading-label target="saveAsTemplate" loading="Menyimpan...">Simpan Template</x-loading-label>
                </x-primary-button>
            </x-modal-actions>
        </form>
    </x-record-form-modal>
</div>
