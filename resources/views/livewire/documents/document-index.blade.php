<div class="space-y-4 sm:space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
        <x-segmented class="overflow-x-auto custom-scrollbar">
            @foreach (\App\Livewire\Documents\DocumentIndex::FILTERS as $key => $label)
                <x-tab-button size="sm" :active="$filter === $key" :count="$counts[$key]" wire:click="setFilter('{{ $key }}')">{{ $label }}</x-tab-button>
            @endforeach
        </x-segmented>

        <div class="flex flex-wrap items-center gap-2 sm:gap-3">
            <x-search-input class="flex-1 lg:w-64" wire:model.live.debounce.400ms="search" placeholder="Cari judul dokumen..." />

            {{-- View Mode Toggle: Tile vs List --}}
            <x-segmented class="shrink-0">
                <x-tab-button size="sm" icon="layout-grid" :active="$viewMode === 'tile'" wire:click="setViewMode('tile')" title="Tampilan Grid (Tile)">
                    <span class="hidden sm:inline">Tile</span>
                </x-tab-button>
                <x-tab-button size="sm" icon="list" :active="$viewMode === 'list'" wire:click="setViewMode('list')" title="Tampilan Tabel (List)">
                    <span class="hidden sm:inline">List</span>
                </x-tab-button>
            </x-segmented>

            <x-secondary-button size="sm" type="button" wire:click="openUseTemplate" class="shrink-0">
                <i data-lucide="copy" class="w-4 h-4"></i>
                <span>Gunakan Template</span>
            </x-secondary-button>

            <x-primary-button size="sm" type="button" wire:click="openUpload" class="shrink-0">
                <i data-lucide="file-up" class="w-4 h-4"></i>
                <span>Unggah PDF</span>
            </x-primary-button>
        </div>
    </div>

    @if ($documents->isEmpty())
        <div class="bg-slate-900 rounded-xl border border-slate-800 overflow-hidden">
            @if ($filter === 'template')
                <x-empty-state icon="copy" title="Belum ada template dokumen" description="Buka dokumen mana saja lalu pilih 'Simpan sebagai Template' untuk menyimpan tata letak penandatangan yang dapat digunakan berulang kali.">
                    <x-secondary-button size="sm" type="button" wire:click="setFilter('semua')">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        <span>Lihat Dokumen</span>
                    </x-secondary-button>
                </x-empty-state>
            @elseif ($search !== '' || $filter !== 'semua')
                <x-empty-state icon="search-x" title="Tidak ada dokumen yang cocok" description="Coba kata kunci lain atau pilih filter Semua." />
            @else
                <x-empty-state icon="file-pen-line" title="Belum ada dokumen" description="Unggah PDF pertama Anda, tentukan siapa saja yang menandatangani, lalu kirim tautannya lewat email atau WhatsApp.">
                    <x-primary-button size="sm" type="button" wire:click="openUpload">
                        <i data-lucide="file-up" class="w-4 h-4"></i>
                        <span>Unggah PDF</span>
                    </x-primary-button>
                </x-empty-state>
            @endif
        </div>
    @elseif ($viewMode === 'list')
        {{-- LIST (TABLE) VIEW --}}
        <x-table :pagination="$documents">
            <thead class="bg-slate-950 text-slate-400 font-semibold uppercase tracking-wider border-b border-slate-800">
                <tr>
                    <x-table.th>{{ $filter === 'template' ? 'Template Dokumen' : 'Dokumen' }}</x-table.th>
                    @if ($filter === 'template')
                        <x-table.th>Bidang Disiapkan</x-table.th>
                        <x-table.th>Penandatangan</x-table.th>
                        <x-table.th>Dibuat</x-table.th>
                    @else
                        <x-table.th>Status</x-table.th>
                        <x-table.th>Penandatangan</x-table.th>
                        <x-table.th>Alur</x-table.th>
                        <x-table.th>Diperbarui</x-table.th>
                    @endif
                    <x-table.th align="right"><span class="sr-only">Aksi</span></x-table.th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-800">
                @foreach ($documents as $document)
                    @php
                        $targetUrl = $document->isDraft() ? route('documents.prepare', $document) : route('documents.show', $document);
                        $percent = $document->signers_count ? round($document->signed_count / $document->signers_count * 100) : 0;
                    @endphp
                    <x-table.tr wire:key="doc-table-{{ $document->id }}">
                        {{-- Dokumen --}}
                        <x-table.td>
                            <a href="{{ $targetUrl }}" wire:navigate class="flex items-center gap-3 group min-w-0">
                                <div class="w-10 h-13 shrink-0 rounded-md overflow-hidden border border-slate-800 bg-white flex items-center justify-center shadow-xs">
                                    @if ($document->thumbnail_path)
                                        <img src="{{ route('documents.thumbnail', $document) }}" alt="" loading="lazy" class="w-full h-full object-cover object-top">
                                    @else
                                        <i data-lucide="file-text" class="w-5 h-5 text-slate-400"></i>
                                    @endif
                                </div>
                                <div class="min-w-0 flex-1">
                                    <p class="text-xs sm:text-sm font-semibold text-slate-100 group-hover:text-emerald-400 transition truncate max-w-xs sm:max-w-md">
                                        {{ $document->title }}
                                    </p>
                                    <p class="text-[11px] text-slate-400 mt-0.5">
                                        {{ $document->total_pages }} halaman · {{ $document->original_filename }}
                                    </p>
                                </div>
                            </a>
                        </x-table.td>

                        @if ($filter === 'template')
                            {{-- Bidang Disiapkan --}}
                            <x-table.td>
                                <span class="text-xs font-mono text-slate-300">{{ $document->fields_count }} bidang</span>
                            </x-table.td>

                            {{-- Penandatangan --}}
                            <x-table.td>
                                <span class="text-xs text-slate-300">{{ $document->signers_count }} penandatangan</span>
                            </x-table.td>

                            {{-- Dibuat --}}
                            <x-table.td>
                                <span class="text-xs text-slate-400 whitespace-nowrap">{{ $document->created_at->diffForHumans() }}</span>
                            </x-table.td>

                            {{-- Aksi --}}
                            <x-table.td align="right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <x-primary-button size="xs" type="button" wire:click="openUseTemplate('{{ $document->id }}')">
                                        <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                                        <span>Gunakan</span>
                                    </x-primary-button>
                                    <x-icon-button icon="trash-2" label="Hapus template" size="xs" wire:click="deleteTemplate('{{ $document->id }}')" wire:confirm="Hapus template ini secara permanen?" class="text-rose-400 hover:text-rose-300 hover:bg-rose-500/10" />
                                </div>
                            </x-table.td>
                        @else
                            {{-- Status --}}
                            <x-table.td>
                                <x-document-status-badge :status="$document->status" />
                            </x-table.td>

                            {{-- Penandatangan --}}
                            <x-table.td>
                                @if ($document->signers_count > 0)
                                    <div class="space-y-1.5 min-w-[160px]">
                                        <div class="flex items-center gap-2">
                                            {{-- Avatars Stack --}}
                                            <div class="flex -space-x-1.5 overflow-hidden">
                                                @foreach ($document->signers->take(4) as $s)
                                                    <span class="w-5 h-5 rounded-full text-[9px] font-bold inline-flex items-center justify-center text-white ring-1 ring-slate-900 shrink-0"
                                                          style="background: {{ $s->color_tag }}"
                                                          title="{{ $s->name }} ({{ $s->status->label() }})">
                                                        {{ strtoupper(substr($s->name, 0, 1)) }}
                                                    </span>
                                                @endforeach
                                                @if ($document->signers_count > 4)
                                                    <span class="w-5 h-5 rounded-full text-[9px] font-bold inline-flex items-center justify-center bg-slate-800 text-slate-300 ring-1 ring-slate-900 shrink-0">
                                                        +{{ $document->signers_count - 4 }}
                                                    </span>
                                                @endif
                                            </div>
                                            <span class="text-[11px] text-slate-400 font-mono">{{ $document->signed_count }}/{{ $document->signers_count }}</span>
                                        </div>
                                        <div class="w-full h-1.5 rounded-full bg-slate-800 overflow-hidden" role="progressbar" aria-valuenow="{{ $document->signed_count }}" aria-valuemax="{{ $document->signers_count }}">
                                            <div class="h-full rounded-full {{ $document->status === \App\Enums\DocumentStatus::Completed ? 'bg-emerald-500' : 'bg-sky-500' }}" style="width: {{ $percent }}%"></div>
                                        </div>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-400">Belum ada</span>
                                @endif
                            </x-table.td>

                            {{-- Alur --}}
                            <x-table.td>
                                <span class="text-xs text-slate-300">{{ $document->signing_order_mode->label() }}</span>
                            </x-table.td>

                            {{-- Diperbarui --}}
                            <x-table.td>
                                <span class="text-xs text-slate-400 whitespace-nowrap">{{ $document->updated_at->diffForHumans() }}</span>
                            </x-table.td>

                            {{-- Aksi --}}
                            <x-table.td align="right">
                                <div class="flex items-center justify-end gap-1.5">
                                    <a href="{{ $targetUrl }}" wire:navigate class="inline-flex items-center gap-1 min-h-[32px] px-2.5 py-1 rounded-lg border border-slate-700 bg-slate-800/40 text-xs font-semibold text-slate-200 hover:bg-slate-800 hover:text-slate-100 transition whitespace-nowrap">
                                        <span>{{ $document->isDraft() ? 'Siapkan' : 'Detail' }}</span>
                                        <i data-lucide="chevron-right" class="w-3.5 h-3.5"></i>
                                    </a>
                                    @unless ($document->status->isInProgress())
                                        <x-icon-button icon="trash-2" label="Hapus dokumen" size="xs" wire:click="confirmDeleteDocument('{{ $document->id }}')" class="text-rose-400 hover:text-rose-300 hover:bg-rose-500/10" />
                                    @endunless
                                </div>
                            </x-table.td>
                        @endif
                    </x-table.tr>
                @endforeach
            </tbody>
        </x-table>
    @else
        {{-- TILE (GRID) VIEW --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-3 sm:gap-4">
            @foreach ($documents as $document)
                @php
                    $targetUrl = $document->isDraft() ? route('documents.prepare', $document) : route('documents.show', $document);
                @endphp
                @if ($filter === 'template')
                    <div wire:key="doc-grid-{{ $document->id }}"
                         class="group flex gap-3 sm:gap-4 p-3 rounded-xl border border-slate-800 bg-slate-900 transition">
                        <div class="w-20 sm:w-24 shrink-0 aspect-[3/4] rounded-lg overflow-hidden border border-slate-800 bg-white flex items-center justify-center shadow-xs">
                            @if ($document->thumbnail_path)
                                <img src="{{ route('documents.thumbnail', $document) }}" alt="" loading="lazy" class="w-full h-full object-cover object-top">
                            @else
                                <i data-lucide="file-text" class="w-7 h-7 text-slate-400"></i>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1 flex flex-col">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="text-sm font-semibold text-slate-100 leading-snug line-clamp-2">{{ $document->title }}</h3>
                            </div>
                            <div class="mt-1.5"><x-badge color="sky">TEMPLATE</x-badge></div>

                            <div class="mt-auto pt-3 space-y-2">
                                <p class="text-[11px] text-slate-400">
                                    {{ $document->total_pages }} hal · {{ $document->fields_count }} bidang · {{ $document->signers_count }} signer
                                </p>
                                <div class="flex items-center gap-1.5 pt-0.5">
                                    <x-primary-button size="xs" type="button" class="flex-1" wire:click="openUseTemplate('{{ $document->id }}')">
                                        <i data-lucide="copy" class="w-3.5 h-3.5"></i>
                                        <span>Gunakan</span>
                                    </x-primary-button>
                                    <x-icon-button icon="trash-2" label="Hapus template" size="xs" wire:click="deleteTemplate('{{ $document->id }}')" wire:confirm="Hapus template ini secara permanen?" class="text-rose-400 hover:text-rose-300 hover:bg-rose-500/10" />
                                </div>
                            </div>
                        </div>
                    </div>
                @else
                    <a wire:key="doc-grid-{{ $document->id }}"
                       href="{{ $targetUrl }}"
                       wire:navigate
                       class="group flex gap-3 sm:gap-4 p-3 rounded-xl border border-slate-800 bg-slate-900 hover:border-emerald-500/40 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500/50 transition">
                        <div class="w-20 sm:w-24 shrink-0 aspect-[3/4] rounded-lg overflow-hidden border border-slate-800 bg-white flex items-center justify-center shadow-xs">
                            @if ($document->thumbnail_path)
                                <img src="{{ route('documents.thumbnail', $document) }}" alt="" loading="lazy" class="w-full h-full object-cover object-top">
                            @else
                                <i data-lucide="file-text" class="w-7 h-7 text-slate-400"></i>
                            @endif
                        </div>
                        <div class="min-w-0 flex-1 flex flex-col">
                            <div class="flex items-start justify-between gap-2">
                                <h3 class="text-sm font-semibold text-slate-100 leading-snug line-clamp-2 group-hover:text-emerald-400 transition">{{ $document->title }}</h3>
                            </div>
                            <div class="mt-1.5"><x-document-status-badge :status="$document->status" /></div>

                            <div class="mt-auto pt-3 space-y-1.5">
                                @if ($document->signers_count > 0)
                                    <div class="flex items-center justify-between text-[11px] text-slate-400">
                                        <span>{{ $document->signed_count }}/{{ $document->signers_count }} sudah tanda tangan</span>
                                        <span class="font-mono">{{ round($document->signed_count / $document->signers_count * 100) }}%</span>
                                    </div>
                                    <div class="h-1.5 rounded-full bg-slate-800 overflow-hidden" role="progressbar" aria-valuenow="{{ $document->signed_count }}" aria-valuemax="{{ $document->signers_count }}">
                                        <div class="h-full rounded-full {{ $document->status === \App\Enums\DocumentStatus::Completed ? 'bg-emerald-500' : 'bg-sky-500' }}" style="width: {{ $document->signers_count ? round($document->signed_count / $document->signers_count * 100) : 0 }}%"></div>
                                    </div>
                                @else
                                    <p class="text-[11px] text-slate-400">Belum ada penandatangan</p>
                                @endif
                                <p class="text-[11px] text-slate-400">
                                    {{ $document->total_pages }} hal · diperbarui {{ $document->updated_at->diffForHumans() }}
                                </p>
                            </div>
                        </div>
                    </a>
                @endif
            @endforeach
        </div>

        @if ($documents->hasPages())
            <div>{{ $documents->links() }}</div>
        @endif
    @endif

    <x-record-form-modal name="upload-document" title="Unggah dokumen PDF" subtitle="Maksimal {{ round(config('paraf.max_upload_kb') / 1024) }} MB, tanpa password" icon="file-up" max-width="2xl" close-action="closeUpload">
        <form wire:submit="uploadDocument" class="space-y-4"
              x-data="pdfUploadPreview({ maxBytes: {{ config('paraf.max_upload_kb') * 1024 }} })">
            <div class="grid grid-cols-1 sm:grid-cols-[1fr_auto] gap-4 items-start">
                <div class="space-y-1.5">
                    <x-input-label for="file" value="File PDF *" />
                    <x-file-input id="file" wire:model="file" :file="$file" accept="application/pdf,.pdf" icon="file-up" label="Pilih atau seret PDF ke sini" x-on:change="inspect($event)" />
                    <p x-show="checking" class="text-[11px] text-slate-400">Memeriksa PDF…</p>
                    <p x-show="pageCount && !error" x-cloak class="text-[11px] text-emerald-400" x-text="`${pageCount} halaman terbaca`"></p>
                    <p x-show="error" x-cloak class="text-xs text-rose-400" x-text="error" role="alert"></p>
                    <x-input-error :messages="$errors->get('file')" />
                </div>
                <div class="hidden sm:flex w-24 aspect-[3/4] rounded-lg border border-slate-800 bg-slate-950 overflow-hidden items-center justify-center">
                    <template x-if="thumbnail"><img :src="thumbnail" alt="Pratinjau halaman pertama" class="w-full h-full object-cover object-top"></template>
                    <template x-if="!thumbnail"><i data-lucide="file-text" class="w-6 h-6 text-slate-500"></i></template>
                </div>
            </div>

            <div>
                <x-input-label for="title" value="Judul dokumen *" />
                <x-text-input id="title" wire:model="title" class="w-full" placeholder="Contoh: Perjanjian Kerja Sama 2026" maxlength="150" />
                <x-input-error :messages="$errors->get('title')" class="mt-1.5" />
            </div>

            <div>
                <x-input-label for="description" value="Pesan untuk penandatangan" />
                <x-textarea id="description" wire:model="description" rows="3" placeholder="Opsional. Mis. mohon tanda tangan di halaman 3 dan paraf tiap halaman." />
                <x-input-error :messages="$errors->get('description')" class="mt-1.5" />
            </div>

            <div class="sm:w-60">
                <x-input-label for="expiry_days" value="Batas waktu tanda tangan *" />
                <div class="relative">
                    <x-text-input id="expiry_days" type="number" wire:model="expiry_days" min="{{ config('paraf.expiry_days.min') }}" max="{{ config('paraf.expiry_days.max') }}" class="w-full pr-14 font-mono" />
                    <span class="absolute inset-y-0 right-0 flex items-center pr-3 pointer-events-none text-xs text-slate-400 font-semibold">hari</span>
                </div>
                <p class="text-[11px] text-slate-400 mt-1">Dihitung sejak dokumen dikirim (1–90 hari).</p>
                <x-input-error :messages="$errors->get('expiry_days')" class="mt-1.5" />
            </div>

            <x-modal-actions>
                <x-secondary-button type="button" wire:click="closeUpload">Batal</x-secondary-button>
                <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="uploadDocument,file" x-bind:disabled="checking || !!error">
                    <x-loading-label target="uploadDocument,file" loading="Mengunggah...">Unggah & Atur Penandatangan</x-loading-label>
                </x-primary-button>
            </x-modal-actions>
        </form>
    </x-record-form-modal>

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

    <x-record-form-modal name="use-template-modal" title="Gunakan Template Dokumen" subtitle="Pilih template untuk membuat dokumen baru secara instan." icon="copy" max-width="lg" close-action="closeUseTemplate">
        <form wire:submit="createFromTemplate" class="space-y-4">
            @if ($availableTemplates->isEmpty())
                <div class="p-4 rounded-xl border border-slate-800 bg-slate-950/60 text-center space-y-2">
                    <i data-lucide="info" class="w-6 h-6 text-slate-400 mx-auto"></i>
                    <p class="text-xs text-slate-300">Belum ada template yang tersimpan.</p>
                    <p class="text-[11px] text-slate-400">Buka salah satu dokumen yang sudah disiapkan, lalu pilih "Simpan sebagai Template" di halaman detail atau editor.</p>
                </div>
                <x-modal-actions>
                    <x-secondary-button type="button" wire:click="closeUseTemplate">Tutup</x-secondary-button>
                </x-modal-actions>
            @else
                <div>
                    <x-input-label for="selectedTemplateId" value="Pilih Template *" />
                    <x-select id="selectedTemplateId" wire:model.live="selectedTemplateId" class="w-full">
                        <option value="">-- Pilih Template --</option>
                        @foreach ($availableTemplates as $tpl)
                            <option value="{{ $tpl->id }}">{{ $tpl->title }} ({{ $tpl->total_pages }} halaman)</option>
                        @endforeach
                    </x-select>
                    <x-input-error :messages="$errors->get('selectedTemplateId')" class="mt-1" />
                </div>

                <div>
                    <x-input-label for="templateDocTitle" value="Judul Dokumen Baru *" />
                    <x-text-input id="templateDocTitle" wire:model="templateDocTitle" class="w-full" placeholder="Mis. Perjanjian Kerja Sama Klien A" maxlength="150" />
                    <x-input-error :messages="$errors->get('templateDocTitle')" class="mt-1" />
                </div>

                <x-modal-actions>
                    <x-secondary-button type="button" wire:click="closeUseTemplate">Batal</x-secondary-button>
                    <x-primary-button type="submit" wire:loading.attr="disabled" wire:target="createFromTemplate">
                        <x-loading-label target="createFromTemplate" loading="Membuat Dokumen...">Buat Dokumen</x-loading-label>
                    </x-primary-button>
                </x-modal-actions>
            @endif
        </form>
    </x-record-form-modal>
</div>
