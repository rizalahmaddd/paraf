<x-layouts.public title="Verifikasi passcode">
    <div class="max-w-md mx-auto">
        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-7 shadow-sm dark:shadow-xl space-y-5">
            <div class="space-y-2">
                <div class="w-11 h-11 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 flex items-center justify-center">
                    <i data-lucide="key-round" class="w-5 h-5"></i>
                </div>
                <h1 class="text-lg font-bold text-slate-100">Masukkan passcode</h1>
                <p class="text-sm text-slate-400">
                    {{ $document->user->name }} melindungi dokumen <span class="text-slate-200 font-semibold">“{{ $document->title }}”</span> dengan passcode 6 digit untuk {{ $signer->name }}.
                    Minta passcode kepada pengirim bila belum menerimanya.
                </p>
            </div>

            @if ($lockSeconds > 0)
                <div class="rounded-xl border border-rose-500/30 bg-rose-500/10 p-3 text-xs text-rose-300" role="alert">
                    Terlalu banyak percobaan salah. Coba lagi dalam {{ (int) ceil($lockSeconds / 60) }} menit.
                </div>
            @endif

            <form method="POST" action="{{ route('sign.passcode', $token) }}" class="space-y-4">
                @csrf
                <div>
                    <x-input-label for="passcode" value="Passcode" />
                    <x-text-input id="passcode" name="passcode" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" autofocus required
                        class="w-full text-center text-2xl font-mono tracking-[0.5em]" placeholder="••••••" :disabled="$lockSeconds > 0" />
                    <x-input-error :messages="$errors->get('passcode')" class="mt-1.5" />
                </div>
                <x-primary-button class="w-full" :disabled="$lockSeconds > 0">Buka Dokumen</x-primary-button>
            </form>
        </section>
    </div>
</x-layouts.public>
