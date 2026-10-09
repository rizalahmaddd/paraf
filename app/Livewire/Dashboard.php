<?php

namespace App\Livewire;

use App\Enums\DocumentStatus;
use App\Livewire\Concerns\WithRealtimeRefresh;
use App\Models\Customer;
use App\Models\Document;
use App\Models\User;
use App\Support\Features;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

#[Layout('layouts.app', ['heading' => 'Dashboard'])]
#[Title('Dashboard')]
class Dashboard extends Component
{
    use WithRealtimeRefresh {
        getListeners as realtimeListeners;
    }

    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        return $this->realtimeListeners() + ['echo-private:App.Models.User.'.Auth::id().',.document.changed' => '$refresh'];
    }

    /**
     * @return array<int, array{title: string, value: string, icon: string, tone: string, subtitle: ?string, href: ?string}>
     */
    public function stats(): array
    {
        $user = Auth::user();
        $stats = [];

        if ($this->showsDocuments()) {
            $counts = Document::query()->ownedBy($user)->notTemplates()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
            $count = fn (DocumentStatus ...$statuses) => (int) collect($statuses)->sum(fn (DocumentStatus $status) => $counts[$status->value] ?? 0);
            $attention = $count(DocumentStatus::Declined, DocumentStatus::Expired);

            $stats[] = ['title' => 'Menunggu Tanda Tangan', 'value' => (string) $count(...DocumentStatus::inProgress()), 'icon' => 'hourglass', 'tone' => 'slate', 'subtitle' => null, 'href' => route('documents.index', ['status' => 'berjalan'])];
            $stats[] = ['title' => 'Selesai', 'value' => (string) $count(DocumentStatus::Completed), 'icon' => 'badge-check', 'tone' => 'slate', 'subtitle' => null, 'href' => route('documents.index', ['status' => 'selesai'])];
            $stats[] = ['title' => 'Draft', 'value' => (string) $count(DocumentStatus::Draft), 'icon' => 'file-pen-line', 'tone' => 'slate', 'subtitle' => 'belum dikirim', 'href' => route('documents.index', ['status' => 'draft'])];
            $stats[] = ['title' => 'Perlu Perhatian', 'value' => (string) $attention, 'icon' => 'triangle-alert', 'tone' => $attention > 0 ? 'amber' : 'slate', 'subtitle' => 'ditolak / kedaluwarsa', 'href' => route('documents.index', ['status' => 'perhatian'])];
        }

        if ($user->can('view-master-data') && Features::enabled('master-data.customers')) {
            $stats[] = [
                'title' => 'Pelanggan',
                'value' => number_format(Customer::count(), 0, ',', '.'),
                'icon' => 'users',
                'tone' => 'slate',
                'subtitle' => number_format(Customer::where('is_active', true)->count(), 0, ',', '.').' aktif',
                'href' => route('master-data.customers'),
            ];
        }

        if ($user->isSuperAdmin()) {
            $stats[] = [
                'title' => 'Pengguna',
                'value' => number_format(User::count(), 0, ',', '.'),
                'icon' => 'user-cog',
                'tone' => 'slate',
                'subtitle' => null,
                'href' => route('settings.roles-and-permissions'),
            ];
        }

        $unread = $user->unreadNotifications()->count();
        $stats[] = [
            'title' => 'Notifikasi Belum Dibaca',
            'value' => (string) $unread,
            'icon' => 'bell',
            'tone' => $unread > 0 ? 'amber' : 'slate',
            'subtitle' => null,
            'href' => null,
        ];

        return $stats;
    }

    /**
     * @return array<int, array{label: string, icon: string, href: string, hint: string}>
     */
    public function shortcuts(): array
    {
        $user = Auth::user();
        $links = [];

        if ($this->showsDocuments()) {
            $links[] = ['label' => 'Unggah Dokumen', 'icon' => 'file-up', 'href' => route('documents.index', ['unggah' => 1]), 'hint' => 'PDF baru untuk ditandatangani'];
            $links[] = ['label' => 'Semua Dokumen', 'icon' => 'file-pen-line', 'href' => route('documents.index'), 'hint' => 'Pantau status dan bagikan tautan'];
        }

        if ($user->can('view-master-data') && Features::enabled('master-data.customers')) {
            $links[] = ['label' => 'Pelanggan', 'icon' => 'users', 'href' => route('master-data.customers'), 'hint' => 'Kelola data pelanggan'];
        }

        $links[] = ['label' => 'Profil Saya', 'icon' => 'user-round', 'href' => route('profile'), 'hint' => 'Ubah nama, kontak, dan password'];

        return $links;
    }

    /**
     * @return Collection<int, Document>
     */
    public function recentDocuments(): Collection
    {
        if (! $this->showsDocuments()) {
            return collect();
        }

        return Document::query()->ownedBy(Auth::user())
            ->whereNot('status', DocumentStatus::Draft->value)
            ->withCount(['signers', 'signers as signed_count' => fn ($query) => $query->where('status', 'SIGNED')])
            ->latest('updated_at')->limit(5)->get();
    }

    private function showsDocuments(): bool
    {
        return Auth::user()->can('manage-documents') && Features::enabled('documents.documents');
    }

    /**
     * @return Collection<int, Activity>|null
     */
    public function recentActivities(): ?Collection
    {
        if (! Auth::user()->can('viewAny', Activity::class)) {
            return null;
        }

        return Activity::query()->with('causer')->latest('id')->limit(8)->get();
    }

    /**
     * @return array<int, string>
     */
    protected function realtimeEvents(): array
    {
        return ['customer.changed'];
    }

    public function render()
    {
        return view('livewire.dashboard', [
            'stats' => $this->stats(),
            'shortcuts' => $this->shortcuts(),
            'activities' => $this->recentActivities(),
            'recentDocuments' => $this->recentDocuments(),
            'showsDocuments' => $this->showsDocuments(),
        ]);
    }
}
