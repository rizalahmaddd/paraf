# Paraf

[![Laravel](https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)](https://www.php.net)
[![Livewire](https://img.shields.io/badge/Livewire-3_+_Volt-FB70A9?logo=livewire&logoColor=white)](https://livewire.laravel.com)
[![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-3-06B6D4?logo=tailwindcss&logoColor=white)](https://tailwindcss.com)
[![Node.js](https://img.shields.io/badge/Node.js-18+-5FA04E?logo=nodedotjs&logoColor=white)](docs/instalasi.md)
[![Pest](https://img.shields.io/badge/Tests-Pest-F472B6?logo=pest&logoColor=white)](docs/instalasi.md#perintah-pengembangan)
[![Demo](https://img.shields.io/badge/Demo-paraf.solusikoding.com-0b6e4f?logo=googlechrome&logoColor=white)](https://paraf.solusikoding.com/)

**Stack:** Laravel 13 · PHP 8.5 · Livewire 3 + Volt + Alpine.js · Tailwind CSS · [pdf-lib](https://pdf-lib.js.org) (engine PDF di Node) · [PDF.js](https://mozilla.github.io/pdf.js/) untuk viewer · [signature_pad](https://github.com/szimek/signature_pad) · [chillerlan/php-qrcode](https://github.com/chillerlan/php-qrcode) · DomPDF untuk lembar audit · Resend & Fonnte (email/WhatsApp) · Laravel Reverb · Pest · Larastan · Pint

Platform tanda tangan elektronik. Pemilik dokumen mengunggah PDF, menaruh kotak tanda tangan untuk tiap penandatangan, lalu membagikan tautan unik lewat email atau WhatsApp. Penandatangan tidak perlu akun. Setelah semua pihak selesai, PDF final disegel bersama lembar riwayat tanda tangan dan kode QR yang bisa dipakai siapa saja untuk mengecek keasliannya.

🌐 **Coba demo:** [paraf.solusikoding.com](https://paraf.solusikoding.com/)

## Fitur

- **Unggah PDF** lalu tentukan siapa saja yang perlu tanda tangan
- **Taruh kotak tanda tangan** langsung di atas halaman PDF: tanda tangan, paraf, tanggal, nama, teks, atau centang
- **Tanda tangan bersamaan atau bergiliran**: semua orang bisa menandatangani sekaligus, atau satu per satu sesuai urutan
- **Kirim lewat email atau WhatsApp**, dengan kode akses opsional untuk tiap penandatangan
- **Penandatangan tidak perlu daftar akun**: cukup buka tautan, lalu tanda tangan dengan mencoret, mengetik nama, atau mengunggah gambar
- **Simpan tanda tangan** di profil supaya tidak perlu menggambar ulang setiap kali
- **Template dokumen** untuk dokumen yang sering dipakai, lengkap dengan posisi kotak dan daftar penandatangannya
- **Pantau progres**: lihat siapa yang sudah dan belum tanda tangan, kirim pengingat, perbaiki kontak yang salah, atau batalkan dokumen
- **Dokumen final tersegel** dengan lembar riwayat tanda tangan dan kode QR
- **Cek keaslian dokumen**: siapa pun bisa memindai QR atau mengunggah PDF untuk memastikan dokumen belum diubah
- **Pengingat otomatis** sebelum batas waktu, dan dokumen kedaluwarsa dengan sendirinya
- **File tersimpan terenkripsi**

## Cara kerja

1. Unggah PDF dan tambahkan penandatangan.
2. Taruh kotak tanda tangan di halaman yang dibutuhkan.
3. Kirim. Setiap penandatangan menerima tautannya masing-masing.
4. Begitu semua selesai, PDF final siap diunduh dan bisa diverifikasi lewat QR-nya.

## Mulai cepat

```bash
composer run setup   # instal dependency, siapkan .env, buat akun admin
composer run dev     # buka http://localhost:8000
```

## Dokumentasi

- [Instalasi & konfigurasi](docs/instalasi.md): kebutuhan server, variabel env, perintah pengembangan
- [Arsitektur](docs/arsitektur.md): peta modul, alur status dokumen, edge case yang ditangani
- [Catatan starter](docs/starter.md): fondasi JagoDev Laravel Starter, cara menambah modul baru

## Dukung & Donasi

Jika proyek ini bermanfaat bagi Anda, dukung pengembangannya melalui **QRIS**:

<p align="center">
  <img src="docs/qris.png" width="240" alt="QRIS Donasi - RZ Printing" />
  <br>
  <em>Scan QRIS menggunakan BCA, Mandiri, BRI, GoPay, OVO, DANA, ShopeePay, atau mobile banking lainnya.</em>
</p>

## Kontak

Dikembangkan oleh **rizalahmaddd**:
- **WhatsApp**: [+62 857-7777-5477](https://wa.me/6285777775477)
- **GitHub**: [@rizalahmaddd](https://github.com/rizalahmaddd)
- **Lokasi**: Kota Malang, Jawa Timur, Indonesia
