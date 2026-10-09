<?php

use App\Services\PdfEngine;
use Illuminate\Support\Facades\File;

function writeTempPdf(string $bytes): string
{
    $path = tempnam(sys_get_temp_dir(), 'paraf').'.pdf';
    File::put($path, $bytes);

    return $path;
}

test('inspect reports page sizes of a normal pdf', function () {
    $report = app(PdfEngine::class)->inspect(writeTempPdf(samplePdf(3)));

    expect($report['ok'])->toBeTrue()
        ->and($report['pages'])->toHaveCount(3)
        ->and($report['pages'][0]['width'])->toEqualWithDelta(595.28, 0.5)
        ->and($report['pages'][0]['rotation'])->toBe(0);
});

test('inspect flags password protected pdfs', function () {
    $report = app(PdfEngine::class)->inspect(writeTempPdf(samplePdf(1, 'rahasia')));

    expect($report)->toMatchArray(['ok' => false, 'reason' => 'encrypted']);
});

test('inspect flags pdfs that carry javascript', function () {
    $objects = [
        '<< /Type /Catalog /Pages 2 0 R /OpenAction 4 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] >>',
        '<< /S /JavaScript /JS (app.alert\\(1\\)) >>',
    ];
    $pdf = "%PDF-1.4\n";
    $offsets = [];
    foreach ($objects as $index => $object) {
        $offsets[] = strlen($pdf);
        $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
    }
    $xref = strlen($pdf);
    $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
    foreach ($offsets as $offset) {
        $pdf .= sprintf("%010d 00000 n \n", $offset);
    }
    $pdf .= 'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";

    $report = app(PdfEngine::class)->inspect(writeTempPdf($pdf));

    expect($report['ok'])->toBeFalse()
        ->and($report['reason'])->toBe('unsafe')
        ->and($report['features'])->toContain('JavaScript');
});

test('inspect rejects files that are not pdf', function () {
    $report = app(PdfEngine::class)->inspect(writeTempPdf('hello world'));

    expect($report)->toMatchArray(['ok' => false, 'reason' => 'invalid']);
});

test('bake and append produce a valid pdf with the certificate pages added', function () {
    $engine = app(PdfEngine::class);
    $input = writeTempPdf(samplePdf(2));
    $image = tempnam(sys_get_temp_dir(), 'sig').'.png';
    File::put($image, base64_decode(substr(samplePngDataUrl(), 22)));

    $baked = tempnam(sys_get_temp_dir(), 'baked').'.pdf';
    $engine->bake($input, $baked, [
        ['page' => 1, 'x' => 0.1, 'y' => 0.7, 'w' => 0.3, 'h' => 0.08, 'type' => 'SIGNATURE', 'image' => $image],
        ['page' => 2, 'x' => 0.1, 'y' => 0.2, 'w' => 0.3, 'h' => 0.04, 'type' => 'TEXT', 'value' => 'Budi Santoso — 3201'],
        ['page' => 2, 'x' => 0.5, 'y' => 0.2, 'w' => 0.04, 'h' => 0.03, 'type' => 'CHECKBOX', 'value' => '1'],
    ]);

    $final = tempnam(sys_get_temp_dir(), 'final').'.pdf';
    $engine->append($baked, writeTempPdf(samplePdf(1)), $final, 'Judul');

    expect($engine->inspect($final)['pages'])->toHaveCount(3)
        ->and(hash_file('sha256', $baked))->not->toBe(hash_file('sha256', $input));
});
