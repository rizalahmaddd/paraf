@php
    $wib = fn ($time) => $time ? $time->copy()->timezone('Asia/Jakarta')->locale('id')->isoFormat('D MMMM YYYY, HH:mm:ss').' WIB' : '-';
    $utc = fn ($time) => $time ? $time->copy()->utc()->format('Y-m-d H:i:s').' UTC' : '';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Sertifikat Penyelesaian - {{ $document->title }}</title>
    <style>
        @page { margin: 28px 34px; }
        * { font-family: 'DejaVu Sans', sans-serif; }
        body { color: #0f172a; font-size: 9px; line-height: 1.45; }
        h1 { font-size: 15px; margin: 0; }
        h2 { font-size: 10.5px; margin: 14px 0 6px; padding-bottom: 3px; border-bottom: 1px solid #cbd5e1; }
        .muted { color: #475569; }
        .mono { font-family: 'DejaVu Sans Mono', monospace; font-size: 8px; word-break: break-all; }
        .brand { display: inline-block; width: 26px; height: 26px; border-radius: 6px; background: #059669; color: #fff; font-weight: bold; font-size: 14px; text-align: center; line-height: 26px; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; padding: 2px 0; }
        .label { width: 34%; color: #475569; }
        .card { border: 1px solid #cbd5e1; border-radius: 6px; padding: 8px 10px; margin-bottom: 8px; page-break-inside: avoid; }
        .swatch { display: inline-block; width: 8px; height: 8px; border-radius: 4px; margin-right: 4px; }
        .sig { height: 46px; border: 1px dashed #cbd5e1; border-radius: 4px; text-align: center; background: #fff; }
        .sig img { max-height: 42px; max-width: 160px; margin-top: 2px; }
        .badge { display: inline-block; padding: 1px 6px; border-radius: 3px; background: #ecfdf5; color: #047857; font-weight: bold; font-size: 8px; }
        .footer { margin-top: 12px; padding-top: 8px; border-top: 1px solid #cbd5e1; font-size: 8px; color: #475569; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td style="width: 70%">
                <span class="brand">P</span>
                <span style="font-size: 12px; font-weight: bold; margin-left: 6px;">{{ \App\Support\Branding::appName() }}</span>
                <h1 style="margin-top: 8px;">Sertifikat Penyelesaian Tanda Tangan Elektronik</h1>
                <div class="muted">Certificate of Completion</div>
            </td>
            <td style="text-align: right;">
                <img src="{{ $qrCode }}" alt="QR verifikasi" style="width: 92px; height: 92px;">
                <div class="muted" style="font-size: 7px;">Pindai untuk verifikasi</div>
            </td>
        </tr>
    </table>

    <h2>Ringkasan dokumen</h2>
    <table>
        <tr><td class="label">Judul</td><td><strong>{{ $document->title }}</strong></td></tr>
        <tr><td class="label">ID dokumen</td><td class="mono">{{ $document->id }}</td></tr>
        <tr><td class="label">Pemilik / pengirim</td><td>{{ $document->user->name }} ({{ $document->user->email }})</td></tr>
        <tr><td class="label">Jumlah halaman asli</td><td>{{ $document->total_pages }} halaman</td></tr>
        <tr><td class="label">Alur penandatanganan</td><td>{{ $document->signing_order_mode->label() }}</td></tr>
        <tr><td class="label">Dikirim</td><td>{{ $wib($document->sent_at) }} <span class="muted">({{ $utc($document->sent_at) }})</span></td></tr>
        <tr><td class="label">Selesai</td><td>{{ $wib($completedAt) }} <span class="muted">({{ $utc($completedAt) }})</span> <span class="badge">SELESAI</span></td></tr>
    </table>

    <h2>Integritas kriptografis (SHA-256)</h2>
    <table>
        <tr><td class="label">Dokumen awal (sebelum ditandatangani)</td><td class="mono">{{ $document->original_hash_sha256 }}</td></tr>
        <tr><td class="label">Dokumen final (setelah semua tanda tangan digabung, halaman 1–{{ $document->total_pages }})</td><td class="mono">{{ $signedHash }}</td></tr>
    </table>
    <div class="muted" style="margin-top: 4px;">Hash file lengkap beserta lembar sertifikat ini tercantum di halaman verifikasi publik.</div>

    <h2>Riwayat para pihak</h2>
    @foreach ($signers as $entry)
        @php $signer = $entry['signer']; @endphp
        <div class="card">
            <table>
                <tr>
                    <td style="width: 62%;">
                        <div style="font-size: 10px; font-weight: bold;"><span class="swatch" style="background: {{ $signer->color_tag }}"></span>{{ $signer->signing_order }}. {{ $signer->name }}</div>
                        <div class="muted">{{ collect([$signer->email, $signer->phone])->filter()->implode(' · ') ?: '-' }}</div>
                        <table style="margin-top: 4px;">
                            <tr><td class="label">Dokumen dibuka</td><td>{{ $wib($signer->viewed_at) }}</td></tr>
                            <tr><td class="label">Ditandatangani</td><td>{{ $wib($signer->signed_at) }} <span class="muted">{{ $utc($signer->signed_at) }}</span></td></tr>
                            <tr><td class="label">Alamat IP</td><td class="mono">{{ $signer->signed_ip_address ?? '-' }}</td></tr>
                            <tr><td class="label">Perangkat (user agent)</td><td style="font-size: 7.5px;">{{ $signer->signed_user_agent ?? '-' }}</td></tr>
                            <tr><td class="label">Metode otentikasi</td><td>Tautan unik bertoken (magic link){{ $entry['passcode_used'] ? ' + passcode 6 digit' : '' }}</td></tr>
                        </table>
                    </td>
                    <td style="padding-left: 10px;">
                        <div class="muted" style="font-size: 7.5px;">Tanda tangan</div>
                        <div class="sig">@if ($entry['signature'])<img src="{{ $entry['signature'] }}" alt="">@endif</div>
                        @if ($entry['initial'])
                            <div class="muted" style="font-size: 7.5px; margin-top: 4px;">Paraf</div>
                            <div class="sig" style="height: 30px;"><img src="{{ $entry['initial'] }}" alt="" style="max-height: 26px;"></div>
                        @endif
                    </td>
                </tr>
            </table>
        </div>
    @endforeach

    <h2>Validasi publik</h2>
    <div>Keaslian dokumen dapat diperiksa kapan saja di: <span class="mono">{{ $verifyUrl }}</span></div>

    <div class="footer">
        Dokumen ini ditandatangani secara elektronik melalui {{ \App\Support\Branding::appName() }}. Tanda tangan elektronik di atas dibuat dengan persetujuan eksplisit para pihak
        dan memenuhi ketentuan Pasal 11 Undang-Undang Nomor 11 Tahun 2008 tentang Informasi dan Transaksi Elektronik sebagaimana telah diubah terakhir dengan
        Undang-Undang Nomor 1 Tahun 2024, serta Peraturan Pemerintah Nomor 71 Tahun 2019 tentang Penyelenggaraan Sistem dan Transaksi Elektronik: data pembuatan
        tanda tangan terkait hanya kepada penanda tangan, setiap perubahan setelah penandatanganan dapat diketahui melalui nilai hash di atas, dan identitas serta
        persetujuan penanda tangan tercatat dalam jejak audit ini.
    </div>
</body>
</html>
