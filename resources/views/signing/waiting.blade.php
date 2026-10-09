<x-layouts.public title="Menunggu giliran">
    <div class="max-w-md mx-auto"
         x-data="{ timer: null }"
         x-init="timer = setInterval(async () => {
            try {
                const response = await fetch(@js(route('sign.status', $token)), { headers: { Accept: 'application/json' } });
                const status = await response.json();
                if (status.is_turn || status.closed) window.location.reload();
            } catch (e) {}
         }, 30000)">
        <section class="rounded-2xl border border-slate-800 bg-slate-900 p-5 sm:p-7 shadow-sm dark:shadow-xl space-y-4 text-center">
            <div class="w-12 h-12 mx-auto rounded-full bg-sky-500/10 border border-sky-500/20 text-sky-400 flex items-center justify-center">
                <i data-lucide="hourglass" class="w-6 h-6"></i>
            </div>
            <h1 class="text-lg font-bold text-slate-100">Menunggu giliran</h1>
            <p class="text-sm text-slate-400">
                Dokumen <span class="text-slate-200 font-semibold">“{{ $document->title }}”</span> sedang ditinjau oleh
                <span class="text-slate-200 font-semibold">{{ $currentSigners->pluck('name')->join(', ', ' dan ') }}</span>.
                Tautan Anda akan aktif otomatis setelahnya.
            </p>
            <p class="text-[11px] text-slate-400">Halaman ini memeriksa ulang setiap 30 detik. Anda juga boleh menutupnya dan membuka tautan yang sama nanti.</p>
        </section>
    </div>
</x-layouts.public>
