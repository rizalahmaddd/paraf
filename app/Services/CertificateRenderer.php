<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Enums\FieldType;
use App\Models\Document;
use App\Models\DocumentField;
use App\Models\Signer;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonInterface;
use chillerlan\QRCode\Output\QRGdImagePNG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * "Sertifikat Penyelesaian" page appended to every sealed PDF.
 */
class CertificateRenderer
{
    public function __construct(private DocumentStorage $storage) {}

    public function render(Document $document, string $signedHash, CarbonInterface $completedAt): string
    {
        $verifyUrl = route('verify.show', $document);

        $signers = $document->signers->map(fn (Signer $signer) => [
            'signer' => $signer,
            'signature' => $this->signaturePreview($document, $signer, FieldType::Signature),
            'initial' => $this->signaturePreview($document, $signer, FieldType::Initial),
            'passcode_used' => $document->auditLogs()
                ->where('signer_id', $signer->id)
                ->where('event_type', AuditEvent::PasscodeVerified)
                ->exists(),
        ]);

        return Pdf::loadView('pdf.certificate', [
            'document' => $document,
            'signers' => $signers,
            'signedHash' => $signedHash,
            'completedAt' => $completedAt,
            'verifyUrl' => $verifyUrl,
            'qrCode' => $this->qrCode($verifyUrl),
        ])->setPaper('a4')->output();
    }

    private function signaturePreview(Document $document, Signer $signer, FieldType $type): ?string
    {
        $field = $document->fields->first(
            fn (DocumentField $field) => $field->signer_id === $signer->id && $field->field_type === $type && filled($field->field_value),
        );

        if ($field === null || ! $this->storage->exists($field->field_value)) {
            return null;
        }

        return 'data:image/png;base64,'.base64_encode($this->storage->get($field->field_value));
    }

    private function qrCode(string $url): string
    {
        $options = new QROptions([
            'outputInterface' => QRGdImagePNG::class,
            'outputBase64' => true,
            'scale' => 6,
            'addQuietzone' => true,
        ]);

        return (new QRCode($options))->render($url);
    }
}
