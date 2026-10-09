<div class="space-y-4 sm:space-y-6">
    {{-- Satu titik fokus di layar ini (DESIGN.md): panel sambutan, satu-satunya elemen dengan gradient + shadow. --}}
    <div class="relative overflow-hidden rounded-2xl bg-white dark:bg-gradient-to-r dark:from-slate-900 dark:via-slate-900 dark:to-slate-800/90 border border-slate-200 dark:border-slate-800 p-4 sm:p-6 md:p-8 shadow-sm dark:shadow-xl">
        <div class="space-y-2 max-w-2xl">
            <p class="text-[11px] font-medium text-slate-500 dark:text-slate-400">{{ now()->isoFormat('dddd, D MMMM Y') }}</p>
            <h2 class="text-xl sm:text-2xl md:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
                {{ __('Selamat datang, :name', ['name' => auth()->user()->name]) }}
            </h2>
            @if (auth()->user()->getRoleNames()->isEmpty())
                <p class="text-xs md:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    {{ __('Akun Anda belum punya peran. Menu kerja muncul setelah Superadmin menetapkan peran untuk akun ini.') }}
                </p>
            @elseif ($showsDocuments)
                <p class="text-xs md:text-sm text-slate-600 dark:text-slate-300 leading-relaxed">
                    {{ __('Unggah PDF, tandai kotak tanda tangan, lalu kirim tautannya lewat email atau WhatsApp. Penandatangan tidak perlu membuat akun.') }}
                </p>
                <div class="pt-2">
                    <a href="{{ route('documents.index', ['unggah' => 1]) }}" wire:navigate class="inline-flex items-center gap-2 min-h-[44px] px-4 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-xs font-bold text-slate-950 transition">
                        <i data-lucide="file-up" class="w-4 h-4"></i> {{ __('Unggah Dokumen') }}
                    </a>
                </div>
            @endif
        </div>
    </div>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        @foreach ($stats as $stat)
            <x-dashboard.stat :title="$stat['title']" :value="$stat['value']" :icon="$stat['icon']" :tone="$stat['tone']"
                :subtitle="$stat['subtitle']" :href="$stat['href']" />
        @endforeach
    </div>

    <x-dashboard.shortcuts :links="$shortcuts" />

    @if ($recentDocuments->isNotEmpty())
        <section class="rounded-xl border border-slate-800 bg-slate-900/80 overflow-hidden">
            <h2 class="px-4 py-3 border-b border-slate-800 text-sm font-bold text-slate-100">{{ __('Dokumen terbaru') }}</h2>
            <ul class="divide-y divide-slate-800">
                @foreach ($recentDocuments as $document)
                    <li>
                        <a href="{{ route('documents.show', $document) }}" wire:navigate class="flex items-center gap-3 px-4 py-3 min-h-[44px] hover:bg-slate-800/50 transition">
                            <i data-lucide="file-text" class="w-4 h-4 text-slate-400 shrink-0"></i>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium text-slate-200 truncate">{{ $document->title }}</span>
                                <span class="block text-[11px] text-slate-400">{{ $document->signed_count }}/{{ $document->signers_count }} sudah tanda tangan · {{ $document->updated_at->diffForHumans() }}</span>
                            </span>
                            <x-document-status-badge :status="$document->status" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif

    @if ($activities !== null)
        <x-dashboard.activity-feed :activities="$activities" />
    @endif
</div>
