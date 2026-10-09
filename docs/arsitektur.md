# Arsitektur

## Peta modul

| Bagian | Lokasi |
|---|---|
| Upload & validasi PDF (password, script, file tersemat) | `app/Services/DocumentIngestor.php`, `resources/node/pdf-engine.cjs` |
| Kirim, ingatkan, koreksi kontak, batalkan, kedaluwarsa | `app/Services/DocumentWorkflow.php` |
| Template dokumen | `app/Services/DocumentTemplateService.php` |
| Passcode, tanda tangan atomik (`lockForUpdate`), tolak | `app/Services/SigningService.php` |
| Penyegelan PDF + sertifikat | `app/Jobs/ProcessCompletedDocumentPdf.php`, `app/Services/CertificateRenderer.php`, `resources/views/pdf/certificate.blade.php` |
| Halaman owner (Livewire) | `app/Livewire/Documents/*` |
| Tanda tangan tersimpan di profil | `resources/views/livewire/profile/manage-signatures-form.blade.php` |
| Halaman signer & verifikasi publik | `app/Http/Controllers/SigningController.php`, `VerifyDocumentController.php`, `resources/views/signing/*`, `resources/views/verify/*` |
| Editor kotak, viewer PDF virtual, tanda tangan (JS) | `resources/js/paraf/*` |

Akun yang mendaftar sendiri otomatis mendapat peran `owner` (izin `documents.manage`). Modul contoh Pelanggan dari starter dimatikan lewat Pengaturan Fitur saat `app:install`/seeder.

## Status dokumen

`Draft` → `Menunggu tanda tangan` → `Sebagian ditandatangani` → `Selesai`, dengan jalur keluar `Ditolak`, `Kedaluwarsa`, atau `Dibatalkan`. Setiap perpindahan status dan aksi penandatangan tercatat di audit trail dokumen.

## Edge case yang ditangani

Tanda tangan atomik dengan `lockForUpdate` supaya dua penandatangan yang submit bersamaan tidak saling menimpa · tautan penandatangan yang sudah selesai, ditolak, atau kedaluwarsa · PDF terenkripsi password, berisi script, atau file tersemat ditolak saat unggah · halaman PDF berotasi dan ukuran halaman campuran saat memetakan koordinat kanvas ke PDF points · penandatangan tanpa email diarahkan ke berbagi via WhatsApp · penyegelan gagal bisa diulang tanpa mengulang tanda tangan · throttle untuk endpoint penandatangan · rotasi `APP_KEY` membuat file lama tidak terbaca (lihat konfigurasi).
