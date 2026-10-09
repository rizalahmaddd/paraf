# Catatan Starter (JagoDev Laravel Starter)

Paraf dibangun di atas JagoDev Laravel Starter, jadi fondasi berikut ikut tersedia:

- **Auth**: login dengan username/email/nomor HP, OTP WhatsApp (Fonnte), register, reset password, verifikasi email, profil.
- **RBAC**: `spatie/laravel-permission`, dengan halaman Peran & Perizinan (matriks izin, peran kustom, penugasan peran ke user). Superadmin melewati semua cek izin.
- **Sakelar fitur**: Superadmin bisa mematikan modul/fitur mana pun dari Pengaturan Fitur tanpa menyentuh data maupun izin.
- **Audit trail aplikasi**: setiap create/update/delete model `Auditable` tercatat beserta snapshot before/after, ditambah log login dan ekspor, di halaman Log Aktivitas.
- **REST API v1**: token Sanctum, FormRequest + Resource, dan dokumentasi OpenAPI yang digenerate otomatis (Scalar di `/docs/api`).
- **Realtime**: Reverb + Echo untuk refresh tabel dan lonceng notifikasi tanpa reload.
- **Backup & restore**, **branding** (nama, logo, kop surat), dan **UI kit** komponen Blade dengan tema gelap/terang serta layout mobile.

## Memulai Project Baru

1. Di GitHub, klik **Use this template** → buat repository baru, lalu clone.
2. Jalankan setup:

   ```bash
   composer run setup
   ```

   Perintah ini menginstal dependency, membuat `.env` + `APP_KEY`, lalu menjalankan `php artisan app:install`. Installer ini menjalankan migrasi, membuat peran & izin, mengisi nama aplikasi/perusahaan, dan membuat akun superadmin pertama. Terakhir, aset frontend dibuild. Kalau pertanyaan installer tidak muncul (terminal non-interaktif), jalankan `php artisan app:install` sendiri setelahnya.

3. Jalankan server web, queue worker, scheduler, Vite, dan Reverb sekaligus:

   ```bash
   composer run dev
   ```

   Buka `http://localhost:8000`. Ganti `REVERB_PORT` di `.env` kalau `8085` bentrok dengan service lain.

Installer juga bisa dijalankan tanpa interaksi (mis. di server):

```bash
php artisan app:install --no-interaction \
  --app-name="Nama Aplikasi" --company="PT Nama Perusahaan" \
  --name="Admin" --email=admin@perusahaan.id --username=admin --password='rahasia-kuat'
```

Tambahkan `--demo` untuk mengisi pelanggan contoh.

### Data demo untuk pengembangan

`php artisan migrate:fresh --seed` membuat satu akun per peran, semua dengan password `password`:

| Peran | Email |
|---|---|
| Superadmin | superadmin@example.test |
| Admin | admin@example.test |
| Staff | staff@example.test |

Jangan jalankan seeder demo di production; pakai `app:install`.

## Checklist Setelah Membuat Project dari Template

- [ ] Ubah `name` dan `description` di `composer.json`, serta `APP_NAME` dan `REVERB_APP_*` di `.env.example`.
- [ ] Sesuaikan `DESIGN.md` kalau karakter aplikasinya berbeda.
- [ ] Ganti foto latar halaman login di `resources/views/layouts/guest.blade.php`.
- [ ] Atur peran bawaan di `RoleSeeder` dan izin bawaan di `PermissionSeeder::DEFAULT_ROLE_PERMISSIONS`.
- [ ] Isi `FONNTE_TOKEN` kalau login OTP WhatsApp dipakai.
- [ ] Deploy: set repository variable `DEPLOY_PATH` (dan `PHP_BIN` bila perlu) untuk `.github/workflows/deploy.yml`. Workflow ini sengaja hanya jalan manual (`workflow_dispatch`); tambahkan trigger `push` kalau self-hosted runner sudah siap.
- [ ] Hapus modul contoh Pelanggan kalau tidak dipakai (lihat daftar file di bawah).

## Peta Arsitektur

| Bagian | Lokasi |
|---|---|
| Menu sidebar, flyout, bottom nav mobile, pencarian menu | `app/Support/Navigation.php` |
| Daftar modul & fitur yang bisa dimatikan | `app/Support/Features.php` |
| Daftar izin & izin bawaan per peran | `database/seeders/PermissionSeeder.php` |
| Gate lintas modul (`view-master-data`, dll.) | `app/Providers/AppServiceProvider.php` |
| Plumbing CRUD Livewire (search, sort, paginasi, modal, hapus, ekspor) | `app/Livewire/Concerns/WithCrudActions.php`, `WithDataTable.php` |
| Refresh realtime per komponen | `app/Livewire/Concerns/WithRealtimeRefresh.php` + `app/Events/Concerns/BroadcastsToDashboard.php` |
| Audit trail | trait `app/Models/Concerns/Auditable.php`, label di `app/Support/Audit/AuditTrail.php` |
| Pencarian global (⌘K) | `resources/views/livewire/layout/global-search.blade.php` |
| Generator OpenAPI | `app/Support/OpenApi/` (atribut `#[ApiTag]`, `#[ApiQuery]`, `#[ApiResponse]` di controller) |
| Nomor dokumen otomatis yang aman dari race condition | `app/Services/DocumentNumberGenerator.php` |
| Branding & kop surat | `app/Support/Branding.php`, `resources/views/components/print-letterhead.blade.php` |

## Menambah Modul Baru

Salin pola modul **Pelanggan**. File yang terlibat:

```
app/Models/Customer.php                                   model (Auditable, event realtime, notifikasi)
database/migrations/*_create_customers_table.php
database/factories/CustomerFactory.php
database/seeders/MasterDataSeeder.php                     data demo
app/Livewire/MasterData/Customers.php                     daftar + form modal (WithCrudActions)
app/Livewire/MasterData/CustomerShow.php                  halaman detail + riwayat perubahan
resources/views/livewire/master-data/customers.blade.php
resources/views/livewire/master-data/customer-show.blade.php
resources/views/print/customer.blade.php                  dokumen cetak
app/Http/Controllers/PrintController.php
routes/master-data.php                                    route web
app/Http/Controllers/Api/V1/MasterData/CustomerController.php
app/Http/Requests/Api/V1/MasterData/CustomerRequest.php
app/Http/Resources/V1/MasterData/CustomerResource.php
routes/api/v1/master-data.php                             route API (middleware feature:...)
app/Events/CustomerChanged.php                            broadcast realtime
app/Listeners/NotifyOfNewCustomer.php
app/Notifications/CustomerCreatedNotification.php
tests/Feature/MasterData/*, tests/Feature/Api/MasterDataApiTest.php
```

Lalu daftarkan modulnya di beberapa registry:

1. **Izin**: tambah grup di `PermissionSeeder::PERMISSION_GROUPS` dan isi `DEFAULT_ROLE_PERMISSIONS`. Halaman Peran & Perizinan membacanya otomatis.
2. **Sakelar fitur**: tambah modul/fitur di `Features::MODULES` beserta pola nama route-nya. Route web yang tidak terdaftar selalu terbuka. Route API memakai middleware `feature:modul.fitur`.
3. **Menu**: tambah entri di `Navigation::items()` dengan `route`, `icon`, `feature`, `can`, dan `keywords` untuk pencarian menu. Tandai `mobile => true` kalau layak masuk bottom nav.
4. **Pencarian global**: tambah satu bagian di `$sections` pada `global-search.blade.php`.
5. **Audit trail**: pakai trait `Auditable` di model dan tambah label di `AuditTrail::SUBJECT_LABELS`.
6. **Dokumentasi API** terbentuk otomatis dari FormRequest, Resource, dan atribut `#[ApiTag]`. Cek hasilnya di `/docs/api`.
7. Jalankan `php artisan test --compact`. `FeatureTogglesTest` gagal kalau ada route yang belum terdaftar di `Features::MODULES`, dan `ApiDocumentationTest` gagal kalau ada endpoint API tanpa sakelar fitur atau belum terdokumentasi.

Untuk tautan ke halaman modul lain, pakai `<x-feature-link :href="...">` supaya tautannya otomatis jadi teks biasa saat fiturnya dimatikan.

## Menjalankan Test & Pemeriksaan Kode

```bash
php artisan test --compact     # Pest
vendor/bin/phpstan analyse     # Larastan
vendor/bin/pint                # format kode
```

Workflow `.github/workflows/tests.yml` menjalankan ketiganya di setiap push ke `main` dan setiap pull request.

## Catatan Lingkungan

- Zona waktu default WIB (`APP_TIMEZONE=Asia/Jakarta`) dan bahasa Indonesia (`APP_LOCALE=id`).
- Broadcasting memakai Reverb (`BROADCAST_CONNECTION=reverb`). Kalau Reverb mati, penyimpanan data tetap berhasil: event memakai `ShouldRescue` dan notifikasi di-queue.
- Notifikasi dan backup berjalan lewat queue (`QUEUE_CONNECTION=database`), jadi di production perlu worker (`php artisan queue:work`) dan scheduler (`php artisan schedule:run` tiap menit) untuk backup terjadwal.
- Export dokumentasi API ke file: `php artisan api:docs --output=storage/app/openapi.json`.
