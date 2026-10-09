<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Role;

/**
 * Satu-satunya daftar menu aplikasi: sidebar, flyout sidebar ciut, navigasi bawah mobile, dan
 * pencarian menu di global search membaca dari sini. Modul baru cukup menambah entri di items().
 *
 * Setiap tautan boleh punya:
 * - feature: kunci Features yang harus menyala
 * - can: ability Gate, atau [ability, model] untuk policy
 * - superadmin: true bila hanya untuk Superadmin
 * - mobile: true bila layak jadi tujuan di navigasi bawah mobile (maks. 4 yang lolos izin)
 */
class Navigation
{
    /**
     * @return list<array{label: ?string, items: list<array<string, mixed>>}>
     */
    public static function items(): array
    {
        return [
            [
                'label' => null,
                'items' => [
                    ['label' => 'Dashboard', 'icon' => 'layout-dashboard', 'route' => 'dashboard', 'active' => 'dashboard', 'mobile' => true, 'mobile_label' => 'Beranda', 'keywords' => 'beranda ringkasan home'],
                    ['label' => 'Dokumen', 'icon' => 'file-pen-line', 'route' => 'documents.index', 'active' => 'documents.*', 'feature' => 'documents.documents', 'can' => 'manage-documents', 'mobile' => true, 'keywords' => 'dokumen tanda tangan pdf signer paraf kontrak'],
                ],
            ],
            [
                'label' => 'Data Referensi',
                'items' => [
                    [
                        'key' => 'master-data',
                        'label' => 'Master Data',
                        'icon' => 'database',
                        'feature' => 'master-data',
                        'children' => [
                            ['label' => 'Pelanggan', 'icon' => 'users', 'route' => 'master-data.customers', 'active' => 'master-data.customers*', 'feature' => 'master-data.customers', 'can' => 'view-master-data', 'mobile' => true, 'keywords' => 'customer klien kontak'],
                        ],
                    ],
                ],
            ],
            [
                'label' => 'Sistem',
                'items' => [
                    [
                        'key' => 'reports',
                        'label' => 'Laporan',
                        'icon' => 'bar-chart-3',
                        'feature' => 'reports',
                        'children' => [
                            ['label' => 'Log Aktivitas', 'icon' => 'scroll-text', 'route' => 'reports.activity-log', 'feature' => 'reports.activity-log', 'can' => ['viewAny', Activity::class], 'keywords' => 'audit trail jejak riwayat login'],
                        ],
                    ],
                    [
                        'key' => 'settings',
                        'label' => 'Pengaturan',
                        'icon' => 'settings',
                        'children' => [
                            ['label' => 'Profil Perusahaan', 'icon' => 'building-2', 'route' => 'settings.company-profile', 'feature' => 'settings.company-profile', 'can' => ['update', Setting::class], 'keywords' => 'profil perusahaan setting kop surat logo branding'],
                            ['label' => 'Peran & Perizinan', 'icon' => 'shield-alert', 'route' => 'settings.roles-and-permissions', 'active' => 'settings.roles-and-permissions*', 'feature' => 'settings.roles-and-permissions', 'can' => ['viewAny', Role::class], 'keywords' => 'role permission peran perizinan hak akses user pengguna'],
                            ['label' => 'Pengaturan Fitur', 'icon' => 'toggle-right', 'route' => 'settings.features', 'superadmin' => true, 'keywords' => 'fitur modul aktif nonaktif toggle'],
                            ['label' => 'Backup & Restore', 'icon' => 'database-backup', 'route' => 'settings.backups', 'active' => 'settings.backups*', 'superadmin' => true, 'keywords' => 'backup cadangan restore pulihkan database'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Menu yang boleh dilihat user: tautan yang gagal izin/fiturnya mati dibuang, grup tanpa
     * anak dan bagian kosong ikut hilang.
     *
     * @return list<array{label: ?string, items: list<array<string, mixed>>}>
     */
    public static function forUser(User $user): array
    {
        $sections = [];

        foreach (self::items() as $section) {
            $items = [];

            foreach ($section['items'] as $item) {
                if (isset($item['children'])) {
                    if (isset($item['feature']) && ! Features::enabled($item['feature'])) {
                        continue;
                    }

                    $item['children'] = array_values(array_filter($item['children'], fn (array $link) => self::allows($user, $link)));

                    if ($item['children'] !== []) {
                        $items[] = $item;
                    }
                } elseif (self::allows($user, $item)) {
                    $items[] = $item;
                }
            }

            if ($items !== []) {
                $sections[] = ['label' => $section['label'], 'items' => $items];
            }
        }

        return $sections;
    }

    /**
     * Semua tautan (tanpa grup) yang boleh dibuka user, beserta nama bagian induknya.
     *
     * @return list<array<string, mixed>>
     */
    public static function linksForUser(User $user): array
    {
        $links = [];

        foreach (self::forUser($user) as $section) {
            foreach ($section['items'] as $item) {
                foreach ($item['children'] ?? [$item] as $link) {
                    $links[] = $link + ['section' => isset($item['children']) ? $item['label'] : ($section['label'] ?? 'Beranda')];
                }
            }
        }

        return $links;
    }

    /**
     * @param  array<string, mixed>  $link
     */
    public static function isActive(array $link): bool
    {
        return request()->routeIs($link['active'] ?? $link['route']);
    }

    /**
     * @param  array<string, mixed>  $group
     */
    public static function isGroupActive(array $group): bool
    {
        foreach ($group['children'] ?? [] as $link) {
            if (self::isActive($link)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $link
     */
    private static function allows(User $user, array $link): bool
    {
        if (($link['superadmin'] ?? false) && ! $user->isSuperAdmin()) {
            return false;
        }

        if (isset($link['feature']) && ! Features::enabled($link['feature'])) {
            return false;
        }

        if (isset($link['can'])) {
            $ability = (array) $link['can'];

            if (! $user->can($ability[0], $ability[1] ?? [])) {
                return false;
            }
        }

        return true;
    }
}
