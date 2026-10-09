# Instalasi & Konfigurasi

## Kebutuhan server

- **Node.js 18+ di server**, bukan hanya saat build. Pemeriksaan PDF, penempelan tanda tangan, dan penggabungan sertifikat dijalankan `resources/node/pdf-engine.cjs` (pdf-lib) lewat Laravel Process, jadi `node_modules` (minimal `pdf-lib`) harus ada di server. Path binary diatur `PARAF_NODE_BINARY`.
- **Queue worker** (`php artisan queue:work`) untuk email undangan dan job `ProcessCompletedDocumentPdf`.
- **Scheduler** (`php artisan schedule:run` tiap menit): `paraf:send-reminders` (09:00 WIB, H-3 & H-1), `paraf:expire-documents` (tiap jam), `paraf:cleanup-drafts` (mingguan).
- Batas upload PHP (`upload_max_filesize`, `post_max_size`) minimal sama dengan `PARAF_MAX_UPLOAD_KB`.
- `RESEND_API_KEY` untuk email undangan, `FONNTE_TOKEN` bila memakai OTP WhatsApp.

## Konfigurasi (`config/paraf.php`)

| Env | Default | Keterangan |
|---|---|---|
| `PARAF_DISK` | `local` | Disk privat untuk PDF, thumbnail, dan gambar tanda tangan (bisa `s3`/R2). |
| `PARAF_MAX_UPLOAD_KB` | `25600` | Batas ukuran PDF; juga dipakai aturan upload sementara Livewire. |
| `PARAF_ENCRYPT_FILES` | `true` | File dienkripsi libsodium dengan kunci turunan `APP_KEY`. **Mengganti `APP_KEY` membuat file lama tidak terbaca.** |
| `PARAF_NODE_BINARY` | `node` | Binary Node untuk engine PDF. |

Masa berlaku dokumen (default 14 hari, 1–90), jadwal pengingat, batas percobaan passcode, retensi draft, dan palet warna penandatangan juga diatur di file yang sama.

## Perintah pengembangan

```bash
composer run setup             # dependency, .env, APP_KEY, app:install, build aset
composer run dev               # server, queue, scheduler, Vite, Reverb → http://localhost:8000
php artisan test --compact     # Pest
vendor/bin/phpstan analyse     # Larastan
vendor/bin/pint                # format kode
```
