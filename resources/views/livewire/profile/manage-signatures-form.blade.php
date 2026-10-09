<?php

use App\Models\User;
use App\Services\DocumentStorage;
use App\Services\SigningService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component
{
    private const COLUMNS = ['SIGNATURE' => 'saved_signature_path', 'INITIAL' => 'saved_initial_path'];

    public ?string $savedSignature = null;

    public ?string $savedInitial = null;

    public function mount(): void
    {
        $this->loadSignatures();
    }

    public function loadSignatures(): void
    {
        /** @var User $user */
        $user = Auth::user();
        $this->savedSignature = $user->savedSignatureBase64();
        $this->savedInitial = $user->savedInitialBase64();
    }

    public function saveSpecimen(string $kind, string $dataUrl, SigningService $signing, DocumentStorage $storage): bool
    {
        $column = self::COLUMNS[$kind] ?? null;
        $png = $column ? $signing->decodePng($dataUrl) : false;

        if ($png === false) {
            return false;
        }

        /** @var User $user */
        $user = Auth::user();
        $previous = $user->{$column};
        $path = "users/{$user->id}/signatures/".strtolower($kind).'-'.Str::random(12).'.png';

        $storage->put($path, $png);
        $user->update([$column => $path]);

        if ($previous && $previous !== $path) {
            $storage->delete($previous);
        }

        $this->loadSignatures();

        return true;
    }

    public function deleteSpecimen(string $kind, DocumentStorage $storage): void
    {
        $column = self::COLUMNS[$kind] ?? null;

        /** @var User $user */
        $user = Auth::user();

        if ($column && $user->{$column}) {
            $storage->delete($user->{$column});
            $user->update([$column => null]);
        }

        $this->loadSignatures();
    }
}; ?>

<section class="space-y-6" x-data="profileSignature(@js(['name' => Auth::user()->name]))">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Caveat:wght@600&family=Dancing+Script:wght@600&family=Great+Vibes&family=Sacramento&display=swap">

    <header>
        <h2 class="text-base font-bold text-slate-100 flex items-center gap-2">
            <i data-lucide="signature" class="w-5 h-5 text-emerald-400"></i>
            Tanda Tangan & Paraf Tersimpan
        </h2>
        <p class="mt-1 text-xs text-slate-400">
            Simpan spesimen tanda tangan dan paraf Anda untuk menandatangani dokumen secara cepat dalam 1 klik tanpa perlu menggambar berulang kali.
        </p>
    </header>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        {{-- Card Tanda Tangan --}}
        <div class="rounded-xl border border-slate-800 bg-slate-950/40 p-4 space-y-3 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-200">Tanda Tangan Utama</span>
                    @if ($savedSignature)
                        <span class="inline-flex items-center gap-1 text-[10px] font-medium text-emerald-700 dark:text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20">
                            <i data-lucide="check" class="w-3 h-3"></i> Tersimpan
                        </span>
                    @else
                        <span class="text-[10px] text-slate-400">Belum diatur</span>
                    @endif
                </div>

                <div class="mt-2.5 h-28 rounded-lg border border-slate-800 bg-white flex items-center justify-center overflow-hidden p-2">
                    @if ($savedSignature)
                        <img src="{{ $savedSignature }}" alt="Tanda Tangan Tersimpan" class="max-h-full max-w-full object-contain">
                    @else
                        <div class="text-center text-slate-400 space-y-1">
                            <i data-lucide="pen-tool" class="w-6 h-6 mx-auto text-slate-300"></i>
                            <p class="text-[11px]">Belum ada tanda tangan</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-2 pt-1">
                <x-primary-button size="xs" type="button" x-on:click="open('SIGNATURE')" class="flex-1">
                    <i data-lucide="pen" class="w-3.5 h-3.5"></i>
                    <span>{{ $savedSignature ? 'Ubah' : 'Buat Tanda Tangan' }}</span>
                </x-primary-button>
                @if ($savedSignature)
                    <x-secondary-button size="xs" type="button" wire:click="deleteSpecimen('SIGNATURE')" wire:confirm="Hapus tanda tangan tersimpan?">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5 text-rose-400"></i>
                    </x-secondary-button>
                @endif
            </div>
        </div>

        {{-- Card Paraf --}}
        <div class="rounded-xl border border-slate-800 bg-slate-950/40 p-4 space-y-3 flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold text-slate-200">Paraf (Inisial)</span>
                    @if ($savedInitial)
                        <span class="inline-flex items-center gap-1 text-[10px] font-medium text-emerald-700 dark:text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded-full border border-emerald-500/20">
                            <i data-lucide="check" class="w-3 h-3"></i> Tersimpan
                        </span>
                    @else
                        <span class="text-[10px] text-slate-400">Belum diatur</span>
                    @endif
                </div>

                <div class="mt-2.5 h-28 rounded-lg border border-slate-800 bg-white flex items-center justify-center overflow-hidden p-2">
                    @if ($savedInitial)
                        <img src="{{ $savedInitial }}" alt="Paraf Tersimpan" class="max-h-full max-w-full object-contain">
                    @else
                        <div class="text-center text-slate-400 space-y-1">
                            <i data-lucide="pen-line" class="w-6 h-6 mx-auto text-slate-300"></i>
                            <p class="text-[11px]">Belum ada paraf</p>
                        </div>
                    @endif
                </div>
            </div>

            <div class="flex items-center gap-2 pt-1">
                <x-primary-button size="xs" type="button" x-on:click="open('INITIAL')" class="flex-1">
                    <i data-lucide="pen" class="w-3.5 h-3.5"></i>
                    <span>{{ $savedInitial ? 'Ubah' : 'Buat Paraf' }}</span>
                </x-primary-button>
                @if ($savedInitial)
                    <x-secondary-button size="xs" type="button" wire:click="deleteSpecimen('INITIAL')" wire:confirm="Hapus paraf tersimpan?">
                        <i data-lucide="trash-2" class="w-3.5 h-3.5 text-rose-400"></i>
                    </x-secondary-button>
                @endif
            </div>
        </div>
    </div>

    {{-- Capture Modal --}}
    <x-modal name="capture-profile-specimen" max-width="lg">
        <div class="p-5 sm:p-6 space-y-4">
            <x-modal-header title="Atur Spesimen" icon="signature">
                <span x-text="kind === 'INITIAL' ? 'Paraf ini bisa dipakai untuk mengisi kotak paraf saat Anda menandatangani dokumen.' : 'Tanda tangan ini bisa dipakai untuk mengisi kotak tanda tangan saat Anda menandatangani dokumen.'"></span>
            </x-modal-header>

            {{-- Mode Switcher: Draw, Type, Upload --}}
            <div class="flex items-center gap-1.5 p-1 rounded-lg bg-slate-950 border border-slate-800">
                <button type="button" x-on:click="setMode('draw')"
                    :class="mode === 'draw' ? 'bg-slate-800 text-slate-100 font-semibold' : 'text-slate-400 hover:text-slate-200'"
                    class="flex-1 py-1.5 rounded-md text-xs transition flex items-center justify-center gap-1.5 cursor-pointer">
                    <i data-lucide="pen-tool" class="w-3.5 h-3.5"></i> Gambar
                </button>
                <button type="button" x-on:click="setMode('type')"
                    :class="mode === 'type' ? 'bg-slate-800 text-slate-100 font-semibold' : 'text-slate-400 hover:text-slate-200'"
                    class="flex-1 py-1.5 rounded-md text-xs transition flex items-center justify-center gap-1.5 cursor-pointer">
                    <i data-lucide="type" class="w-3.5 h-3.5"></i> Ketik
                </button>
                <button type="button" x-on:click="setMode('upload')"
                    :class="mode === 'upload' ? 'bg-slate-800 text-slate-100 font-semibold' : 'text-slate-400 hover:text-slate-200'"
                    class="flex-1 py-1.5 rounded-md text-xs transition flex items-center justify-center gap-1.5 cursor-pointer">
                    <i data-lucide="upload" class="w-3.5 h-3.5"></i> Unggah
                </button>
            </div>

            {{-- Draw Mode --}}
            <div x-show="mode === 'draw'" class="space-y-2">
                <div class="relative rounded-xl border border-slate-700 bg-white overflow-hidden">
                    <canvas x-ref="padCanvas" class="block w-full h-44 sm:h-52 touch-none cursor-crosshair"></canvas>
                    <div class="absolute left-6 right-6 bottom-8 border-b border-dashed pointer-events-none" style="border-color:#cbd5e1"></div>
                </div>
                <div class="flex items-center justify-between">
                    <p class="text-[11px] text-slate-400">Gunakan mouse, stylus, atau jari di layar sentuh.</p>
                    <div class="flex gap-1.5">
                        <x-secondary-button size="xs" type="button" x-on:click="undoPad()">
                            <i data-lucide="undo-2" class="w-3 h-3"></i> Undo
                        </x-secondary-button>
                        <x-secondary-button size="xs" type="button" x-on:click="clearPad()">
                            <i data-lucide="eraser" class="w-3 h-3"></i> Bersihkan
                        </x-secondary-button>
                    </div>
                </div>
            </div>

            {{-- Type Mode --}}
            <div x-show="mode === 'type'" x-cloak class="space-y-3">
                <div>
                    <label class="block font-medium text-xs text-slate-300 mb-1" x-text="kind === 'INITIAL' ? 'Inisial Paraf' : 'Nama Lengkap'"></label>
                    <input type="text" x-model="typedText" maxlength="60"
                        class="w-full min-h-[40px] bg-slate-950 border border-slate-800 rounded-lg px-3 py-2 text-sm text-slate-100 focus:outline-none focus:border-emerald-500">
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <template x-for="font in fonts" :key="font">
                        <button type="button" x-on:click="selectedFont = font"
                            class="min-h-[56px] px-3 rounded-lg border-2 bg-white text-left overflow-hidden transition"
                            :class="selectedFont === font ? 'border-emerald-500 ring-2 ring-emerald-500/20' : 'border-slate-700 hover:border-slate-500'">
                            <span class="block truncate text-2xl leading-tight text-slate-900" :style="{ fontFamily: `'${font}', cursive` }" x-text="typedText || (kind === 'INITIAL' ? 'Inisial' : 'Nama Anda')"></span>
                        </button>
                    </template>
                </div>
            </div>

            {{-- Upload Mode --}}
            <div x-show="mode === 'upload'" x-cloak class="space-y-3">
                <label class="flex items-center gap-3 min-h-[52px] px-3 py-2.5 rounded-lg border border-dashed border-slate-700 bg-slate-950 cursor-pointer hover:border-emerald-500">
                    <i data-lucide="image-up" class="w-5 h-5 text-slate-400"></i>
                    <span class="text-xs text-slate-300">Pilih foto tanda tangan di kertas putih (PNG/JPG)</span>
                    <input type="file" accept="image/*" class="sr-only" x-on:change="onUpload($event)">
                </label>
                <div x-show="uploadPreview" class="rounded-xl border border-slate-700 bg-white p-3 flex justify-center">
                    <img :src="uploadPreview" alt="Pratinjau tanda tangan" class="max-h-36 object-contain">
                </div>
            </div>

            <p x-show="error" x-cloak class="text-xs text-rose-400" x-text="error"></p>

            <x-modal-actions>
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', 'capture-profile-specimen')">Batal</x-secondary-button>
                <x-primary-button type="button" x-on:click="save()" x-bind:disabled="saving">
                    <span x-text="saving ? 'Menyimpan…' : 'Simpan ke Profil'"></span>
                </x-primary-button>
            </x-modal-actions>
        </div>
    </x-modal>
</section>
