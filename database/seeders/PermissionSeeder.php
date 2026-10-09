<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Definisi lengkap hak akses (perizinan) sistem yang dikelompokkan per modul. Halaman
     * Peran & Perizinan membaca daftar ini, jadi modul baru cukup menambah grupnya di sini.
     *
     * @var array<string, array<string, array{label: string, description: string}>>
     */
    public const PERMISSION_GROUPS = [
        'Dokumen' => [
            'documents.manage' => [
                'label' => 'Kelola Dokumen Tanda Tangan',
                'description' => 'Mengunggah dokumen, mengatur penandatangan, mengirim, dan mengunduh dokumen milik sendiri.',
            ],
        ],
        'Master Data' => [
            'master-data.view' => [
                'label' => 'Lihat Master Data',
                'description' => 'Melihat daftar dan detail data master, mis. pelanggan.',
            ],
            'master-data.manage' => [
                'label' => 'Kelola Master Data',
                'description' => 'Menambah, mengubah, dan menghapus data master.',
            ],
        ],
        'Laporan & Audit' => [
            'reports.activity.view' => [
                'label' => 'Log Aktivitas (Audit Trail)',
                'description' => 'Melihat seluruh jejak audit aktivitas pengguna sistem.',
            ],
        ],
        'Pengaturan & Keamanan' => [
            'settings.company.manage' => [
                'label' => 'Pengaturan Profil Perusahaan',
                'description' => 'Mengatur nama aplikasi, identitas perusahaan, kop surat cetak, dan logo.',
            ],
            'roles.manage' => [
                'label' => 'Kelola Peran & Perizinan',
                'description' => 'Membuat peran baru, mengubah perizinan, dan mengatur matriks akses.',
            ],
            'users.manage' => [
                'label' => 'Kelola Penugasan Peran Pengguna',
                'description' => 'Menugaskan dan mencabut peran dari akun pengguna sistem.',
            ],
        ],
    ];

    /**
     * Izin bawaan per peran; "Kembalikan ke default" di halaman Peran & Perizinan memakai daftar ini.
     *
     * @var array<string, list<string>>
     */
    public const DEFAULT_ROLE_PERMISSIONS = [
        'superadmin' => ['*'],
        'admin' => [
            'documents.manage',
            'master-data.view', 'master-data.manage',
            'reports.activity.view',
            'settings.company.manage',
            'users.manage',
        ],
        'staff' => [
            'documents.manage',
            'master-data.view',
        ],
        // Default role for self-registered accounts.
        'owner' => [
            'documents.manage',
        ],
    ];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $allPermissions = [];
        foreach (self::PERMISSION_GROUPS as $permissions) {
            foreach ($permissions as $permissionName => $meta) {
                Permission::findOrCreate($permissionName, 'web');
                $allPermissions[] = $permissionName;
            }
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::DEFAULT_ROLE_PERMISSIONS as $roleName => $permissions) {
            $role = Role::findOrCreate($roleName, 'web');

            if ($permissions === ['*']) {
                $role->syncPermissions($allPermissions);
            } else {
                $role->syncPermissions($permissions);
            }
        }
    }
}
