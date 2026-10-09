<x-layouts.public :title="$document->title">
    <x-slot:header>
        <p class="text-sm font-bold text-slate-100 truncate max-w-[52vw] sm:max-w-md">{{ $document->title }}</p>
        <p class="text-[11px] text-slate-400" x-data x-text="`${$store.signing.filled} dari ${$store.signing.total} field wajib terisi`"></p>
    </x-slot:header>

    <x-slot:actions>
        <x-secondary-button size="xs" type="button" x-data x-on:click="$dispatch('open-modal', 'decline-document')">
            <i data-lucide="circle-x" class="w-3.5 h-3.5"></i> <span class="hidden sm:inline">Tolak Dokumen</span><span class="sm:hidden">Tolak</span>
        </x-secondary-button>
    </x-slot:actions>

    <div x-data="signingPage(@js($config))"
         x-effect="$store.signing.filled = filledCount; $store.signing.total = requiredFields.length"
         class="space-y-4">

        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-4 sm:p-5 shadow-sm dark:shadow-xl space-y-2">
            <p class="text-xs text-slate-400">
                <span class="font-semibold text-slate-200">{{ $document->user->name }}</span> meminta tanda tangan Anda
                · berlaku sampai {{ $document->expires_at?->isoFormat('D MMMM Y, HH:mm') }} WIB
            </p>
            <h1 class="text-base sm:text-lg font-bold text-slate-100">Halo {{ $signer->name }}, tinjau dokumen lalu isi kotak berwarna milik Anda.</h1>
            @if ($document->description)
                <p class="text-sm text-slate-300 whitespace-pre-line border-l-2 border-emerald-500/50 pl-3">{{ $document->description }}</p>
            @endif
            <div class="flex items-center gap-2 text-[11px] text-slate-400">
                <span class="inline-block w-3 h-3 rounded-sm border-2" style="border-color: {{ $signer->color_tag }}; background: {{ $signer->color_tag }}22"></span>
                Kotak milik Anda · kotak abu-abu milik penandatangan lain
            </div>
        </section>

        <div x-show="offline || pendingSubmit" x-cloak role="status"
             class="rounded-xl border border-amber-500/30 bg-amber-500/10 p-3 text-xs text-amber-300 flex flex-col sm:flex-row sm:items-center gap-2 justify-between">
            <span class="inline-flex items-start gap-2">
                <i data-lucide="wifi-off" class="w-4 h-4 shrink-0 mt-0.5"></i>
                <span x-text="pendingSubmit ? 'Koneksi terputus saat mengirim. Tanda tangan Anda aman tersimpan di perangkat ini dan akan dikirim ulang otomatis saat internet kembali.' : 'Anda sedang offline. Isian tetap tersimpan di perangkat ini.'"></span>
            </span>
            <x-secondary-button size="xs" type="button" x-show="pendingSubmit" x-on:click="submit()">Kirim Ulang Sekarang</x-secondary-button>
        </div>

        <div x-show="loading" class="rounded-xl border border-slate-800 bg-slate-900 p-10 text-center text-xs text-slate-400">
            <x-spinner class="w-5 h-5 mx-auto mb-2" /> Memuat dokumen…
        </div>
        <div x-show="loadError" x-cloak class="rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-xs text-rose-300" x-text="loadError" role="alert"></div>

        <div x-show="!loading && !loadError" class="space-y-4 sm:space-y-6 mx-auto max-w-[900px]">
            <template x-for="page in pages" :key="page.number">
                <div>
                    <p class="text-[11px] text-slate-400 mb-1" x-text="'Halaman ' + page.number + ' dari ' + pages.length"></p>
                    <div :data-page="page.number" :style="pageStyle(page)" class="relative w-full bg-white rounded-md shadow-lg overflow-hidden">
                        <div data-canvas-slot class="absolute inset-0"></div>

                        <template x-for="field in fieldsOn(page.number)" :key="field.id">
                            <div :data-field="field.id" class="absolute z-10" :style="fieldStyle(field)">
                                {{-- Boxes owned by other signers: visible for context, never interactive. --}}
                                <template x-if="!field.mine">
                                    <div class="w-full h-full rounded-[3px] border-2 border-dashed flex items-center justify-center opacity-50 pointer-events-none"
                                         style="border-color: #94a3b8; background: rgba(148,163,184,.12)">
                                        <span class="truncate px-1 text-[9px] sm:text-[10px] font-semibold" style="color:#475569" x-text="field.done ? '✓ ' + field.owner_name : field.owner_name"></span>
                                    </div>
                                </template>

                                <template x-if="field.mine && (field.type === 'SIGNATURE' || field.type === 'INITIAL')">
                                    <button type="button" x-on:click="activate(field)"
                                        class="signing-field w-full h-full rounded-[3px] flex items-center justify-center focus:outline-none focus-visible:ring-2 focus-visible:ring-offset-1"
                                        :class="{ 'is-filled': isFilled(field), 'is-highlight': highlightId === field.id, 'is-error': errors[field.id] }"
                                        :aria-label="(field.type === 'SIGNATURE' ? 'Tanda tangan' : 'Paraf') + (isFilled(field) ? ' (terisi, ketuk untuk mengganti)' : '')">
                                        <img x-show="isFilled(field)" :src="values[field.id]" alt="" class="w-full h-full object-contain pointer-events-none">
                                        <span x-show="!isFilled(field)" class="inline-flex items-center gap-1 text-[10px] sm:text-xs font-bold px-1 truncate" style="color: var(--field-color)">
                                            <i data-lucide="pen-line" class="w-3.5 h-3.5 shrink-0"></i>
                                            <span x-text="cache[field.type] ? 'Ketuk untuk menempel' : (field.type === 'SIGNATURE' ? 'Tanda tangan' : 'Paraf')"></span>
                                        </span>
                                    </button>
                                </template>

                                <template x-if="field.mine && field.type === 'TEXT'">
                                    <input type="text" maxlength="255" x-model="values[field.id]" x-on:input="afterChange(field)"
                                        :placeholder="field.label || 'Isi di sini'" :aria-label="field.label || 'Isian teks'"
                                        class="signing-field input-bare w-full h-full rounded-[3px] px-1 bg-transparent border-0 focus:ring-2 focus:ring-offset-0"
                                        :class="{ 'is-filled': isFilled(field), 'is-highlight': highlightId === field.id, 'is-error': errors[field.id] }"
                                        :style="{ fontSize: textSize(field, $el.closest('[data-page]'), values[field.id] || field.label || ''), color: '#0f172a' }">
                                </template>

                                <template x-if="field.mine && field.type === 'CHECKBOX'">
                                    <button type="button" x-on:click="activate(field)" role="checkbox" :aria-checked="values[field.id] === true" :aria-label="field.label || 'Checkbox'" :title="field.label"
                                        class="signing-field w-full h-full rounded-[3px] flex items-center justify-center"
                                        :class="{ 'is-filled': values[field.id] === true, 'is-highlight': highlightId === field.id, 'is-error': errors[field.id] }">
                                        <svg x-show="values[field.id] === true" viewBox="0 0 24 24" class="w-4/5 h-4/5" fill="none" stroke="#0f172a" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="4 12 10 18 20 6"/></svg>
                                    </button>
                                </template>

                                <template x-if="field.mine && (field.type === 'DATE' || field.type === 'NAME')">
                                    <div class="signing-field is-filled w-full h-full rounded-[3px] flex items-center px-1 overflow-hidden"
                                         :style="{ fontSize: textSize(field, $el.closest('[data-page]'), field.type === 'DATE' ? config.today : config.signerName), color: '#0f172a' }">
                                        <span class="truncate" x-text="field.type === 'DATE' ? config.today : config.signerName"></span>
                                    </div>
                                </template>

                                <p x-show="errors[field.id]" x-cloak class="absolute left-0 top-full mt-0.5 text-[10px] font-semibold whitespace-nowrap px-1 rounded bg-rose-600 text-white no-dark-invert" x-text="errors[field.id]"></p>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>

        {{-- Guided action: always jumps to the next empty box, then turns into the submit step. --}}
        <div class="fixed inset-x-0 bottom-0 z-30 pointer-events-none pb-[calc(1rem+env(safe-area-inset-bottom))] px-4">
            <div class="max-w-5xl mx-auto flex justify-center sm:justify-end">
                <button type="button" x-on:click="goNext()" x-show="!loading && !loadError"
                    class="pointer-events-auto inline-flex items-center gap-2 min-h-[52px] px-6 rounded-full font-bold text-sm shadow-2xl shadow-slate-950/50 focus:outline-none focus-visible:ring-4 focus-visible:ring-emerald-500/40 transition"
                    :class="complete ? 'bg-emerald-500 hover:bg-emerald-400 text-slate-950' : 'bg-slate-100 hover:bg-white text-slate-900 no-dark-invert'">
                    <i :data-lucide="complete ? 'send' : 'arrow-down'" class="w-4 h-4"></i>
                    <span x-text="complete ? 'Selesai & Kirim' : (filledCount === 0 ? 'Mulai: ke kotak Anda' : 'Lanjut ke kotak berikutnya')"></span>
                </button>
            </div>
        </div>

        <x-modal name="signature-capture" max-width="2xl">
            <div class="p-4 sm:p-6 space-y-4 overflow-y-auto custom-scrollbar">
                <x-modal-header :title="'Buat tanda tangan'" icon="signature" closeable>
                    <span x-text="capture.kind === 'INITIAL' ? 'Paraf Anda akan dipakai untuk semua kotak paraf di dokumen ini.' : 'Tanda tangan Anda akan dipakai untuk semua kotak tanda tangan di dokumen ini.'"></span>
                </x-modal-header>

                {{-- Quick Saved Specimen Preset Option --}}
                <template x-if="(capture.kind === 'SIGNATURE' && config.savedSignature) || (capture.kind === 'INITIAL' && config.savedInitial)">
                    <div class="p-3 rounded-xl border border-emerald-500/30 bg-emerald-500/10 flex items-center justify-between gap-3">
                        <div class="flex items-center gap-2.5 min-w-0">
                            <span class="w-8 h-8 rounded-lg bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 flex items-center justify-center shrink-0">
                                <i data-lucide="sparkles" class="w-4 h-4"></i>
                            </span>
                            <div class="min-w-0">
                                <p class="text-xs font-bold text-emerald-950 dark:text-emerald-300">Spesimen Profil Tersedia</p>
                                <p class="text-[11px] text-emerald-900 dark:text-emerald-200/90 truncate">Gunakan spesimen profil akun Anda tanpa perlu menggambar.</p>
                            </div>
                        </div>
                        <x-primary-button size="xs" type="button" x-on:click="applySavedPreset()" class="shrink-0">
                            Gunakan Profil
                        </x-primary-button>
                    </div>
                </template>

                <x-segmented class="w-full">
                    <x-tab-button size="sm" class="flex-1" x-bind:aria-pressed="capture.mode === 'draw'" x-bind:class="capture.mode === 'draw' ? '!bg-emerald-500/10 !text-emerald-700 dark:!text-emerald-400 !border-emerald-500/30' : ''" x-on:click="setMode('draw')" icon="pen-line">Coret</x-tab-button>
                    <x-tab-button size="sm" class="flex-1" x-bind:aria-pressed="capture.mode === 'type'" x-bind:class="capture.mode === 'type' ? '!bg-emerald-500/10 !text-emerald-700 dark:!text-emerald-400 !border-emerald-500/30' : ''" x-on:click="setMode('type')" icon="type">Ketik</x-tab-button>
                    <x-tab-button size="sm" class="flex-1" x-bind:aria-pressed="capture.mode === 'upload'" x-bind:class="capture.mode === 'upload' ? '!bg-emerald-500/10 !text-emerald-700 dark:!text-emerald-400 !border-emerald-500/30' : ''" x-on:click="setMode('upload')" icon="image-up">Unggah</x-tab-button>
                </x-segmented>

                <div class="flex items-center gap-2">
                    <span class="text-xs text-slate-400">Warna tinta</span>
                    <template x-for="(hex, name) in inks" :key="name">
                        <button type="button" x-on:click="setInk(name)" :aria-pressed="capture.ink === name" :aria-label="name === 'black' ? 'Hitam' : 'Biru formal'"
                            class="w-11 h-11 sm:w-8 sm:h-8 rounded-full border-2 inline-flex items-center justify-center"
                            :class="capture.ink === name ? 'border-emerald-500' : 'border-slate-700'">
                            <span class="w-5 h-5 rounded-full ring-1 ring-slate-500" :style="{ background: hex }"></span>
                        </button>
                    </template>
                </div>

                <div x-show="capture.mode === 'draw'" class="space-y-2">
                    <div class="relative rounded-xl border border-slate-700 bg-white overflow-hidden">
                        <canvas data-pad-canvas class="block w-full h-48 sm:h-56 touch-none cursor-crosshair"></canvas>
                        <div class="absolute left-6 right-6 bottom-10 border-b border-dashed pointer-events-none" style="border-color:#cbd5e1"></div>
                    </div>
                    <div class="flex items-center justify-between">
                        <p class="text-[11px] text-slate-400">Coret dengan jari, stylus, atau mouse di area putih.</p>
                        <div class="flex gap-1">
                            <x-secondary-button size="xs" type="button" x-on:click="undoPad()"><i data-lucide="undo-2" class="w-3.5 h-3.5"></i> Undo</x-secondary-button>
                            <x-secondary-button size="xs" type="button" x-on:click="clearPad()"><i data-lucide="eraser" class="w-3.5 h-3.5"></i> Hapus</x-secondary-button>
                        </div>
                    </div>
                </div>

                <div x-show="capture.mode === 'type'" x-cloak class="space-y-3">
                    <div>
                        <label for="typed-signature" class="block font-medium text-xs text-slate-300 mb-1.5" x-text="capture.kind === 'INITIAL' ? 'Inisial' : 'Nama lengkap'"></label>
                        <input id="typed-signature" type="text" x-model="capture.text" maxlength="60"
                            class="w-full min-h-[44px] bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500">
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2" role="radiogroup" aria-label="Gaya tulisan">
                        <template x-for="font in fonts" :key="font">
                            <button type="button" role="radio" :aria-checked="capture.font === font" x-on:click="capture.font = font"
                                class="min-h-[64px] px-3 rounded-lg border-2 bg-white text-left overflow-hidden"
                                :class="capture.font === font ? 'border-emerald-500' : 'border-slate-700'">
                                <span class="block truncate text-3xl leading-tight" :style="{ fontFamily: `'${font}', cursive`, color: inks[capture.ink] }" x-text="capture.text || 'Nama Anda'"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div x-show="capture.mode === 'upload'" x-cloak class="space-y-3">
                    <label class="flex items-center gap-3 min-h-[56px] px-3 py-2.5 rounded-lg border border-dashed border-slate-700 bg-slate-950 cursor-pointer hover:border-emerald-500 focus-within:ring-2 focus-within:ring-emerald-500">
                        <i data-lucide="image-up" class="w-5 h-5 text-slate-400"></i>
                        <span class="text-xs text-slate-300">Pilih foto tanda tangan di kertas putih</span>
                        <input type="file" accept="image/*" class="sr-only" x-on:change="onUpload($event)">
                    </label>
                    <p class="text-[11px] text-slate-400">Latar putih dihapus otomatis dan kontras tinta dipertegas.</p>
                    <div x-show="capture.uploadPreview" class="rounded-xl border border-slate-700 bg-white p-3 flex justify-center">
                        <img :src="capture.uploadPreview" alt="Pratinjau tanda tangan" class="max-h-40 object-contain">
                    </div>
                </div>

                <p x-show="capture.error" x-cloak class="text-xs text-rose-400" role="alert" x-text="capture.error"></p>

                <x-modal-actions>
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">Batal</x-secondary-button>
                    <x-primary-button type="button" x-on:click="applyCapture()" x-bind:disabled="capture.busy">Pakai Tanda Tangan Ini</x-primary-button>
                </x-modal-actions>
            </div>
        </x-modal>

        <x-modal name="confirm-submit" max-width="lg">
            <div class="p-5 sm:p-6 space-y-4">
                <x-modal-header title="Kirim tanda tangan" icon="badge-check">Periksa sekali lagi. Setelah dikirim, isian tidak bisa diubah.</x-modal-header>
                <label class="flex items-start gap-3 p-3 rounded-xl border border-slate-800 bg-slate-950/40 cursor-pointer">
                    <input type="checkbox" x-model="consent" class="w-5 h-5 mt-0.5 shrink-0 rounded border-slate-700 bg-slate-950 text-emerald-500 focus:ring-emerald-500">
                    <span class="text-xs text-slate-200 leading-relaxed">Dengan ini saya menyetujui isi dokumen ini dan menyepakati bahwa tanda tangan elektronik yang saya buat sah dan mengikat secara hukum.</span>
                </label>
                <p x-show="submitError" x-cloak class="text-xs text-rose-400" role="alert" x-text="submitError"></p>
                <x-modal-actions>
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">Periksa Lagi</x-secondary-button>
                    <x-primary-button type="button" x-on:click="submit()" x-bind:disabled="!consent || submitting">
                        <x-spinner class="w-4 h-4" x-show="submitting" x-cloak />
                        <span x-text="submitting ? 'Mengirim…' : 'Setuju & Kirim Tanda Tangan'"></span>
                    </x-primary-button>
                </x-modal-actions>
            </div>
        </x-modal>

        <x-modal name="decline-document" max-width="lg">
            <div class="p-5 sm:p-6 space-y-4">
                <x-modal-header title="Tolak dokumen ini?" icon="circle-x" tone="rose">Pengirim akan menerima alasan Anda dan semua tautan untuk dokumen ini dinonaktifkan.</x-modal-header>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2" role="radiogroup" aria-label="Alasan penolakan">
                    <template x-for="preset in ['Isi dokumen tidak sesuai', 'Perlu revisi terlebih dahulu', 'Bukan pihak yang tepat', 'Lainnya']" :key="preset">
                        <button type="button" role="radio" :aria-checked="declinePreset === preset" x-on:click="declinePreset = preset"
                            class="min-h-[44px] px-3 rounded-lg border text-xs text-left transition"
                            :class="declinePreset === preset ? 'border-rose-500/50 bg-rose-500/10 text-rose-300' : 'border-slate-800 text-slate-300 hover:border-slate-700'" x-text="preset"></button>
                    </template>
                </div>
                <div>
                    <label for="decline-reason" class="block font-medium text-xs text-slate-300 mb-1.5">Keterangan</label>
                    <textarea id="decline-reason" x-model="declineReason" rows="3" maxlength="900" placeholder="Jelaskan apa yang perlu diperbaiki"
                        class="w-full bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-rose-500"></textarea>
                </div>
                <p x-show="declineError" x-cloak class="text-xs text-rose-400" role="alert" x-text="declineError"></p>
                <x-modal-actions>
                    <x-secondary-button type="button" x-on:click="$dispatch('close')">Kembali</x-secondary-button>
                    <x-danger-button type="button" x-on:click="decline()" x-bind:disabled="declining">
                        <span x-text="declining ? 'Mengirim…' : 'Tolak Dokumen'"></span>
                    </x-danger-button>
                </x-modal-actions>
            </div>
        </x-modal>
    </div>
</x-layouts.public>
