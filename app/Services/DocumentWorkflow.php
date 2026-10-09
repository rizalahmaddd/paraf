<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Enums\DocumentStatus;
use App\Enums\FieldType;
use App\Events\DocumentChanged;
use App\Jobs\ProcessCompletedDocumentPdf;
use App\Models\Document;
use App\Models\Signer;
use App\Models\User;
use App\Notifications\DocumentVoidedNotification;
use App\Notifications\SignatureRequestNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * Owner-side lifecycle: send, remind, correct contacts, void, expire, retry sealing.
 */
class DocumentWorkflow
{
    public function __construct(private DocumentStorage $storage) {}

    /**
     * @throws ValidationException
     */
    public function send(Document $document, User $owner, bool $viaEmail): void
    {
        $document->loadMissing('signers.fields');

        if ($document->isTemplate()) {
            $this->fail('Template tidak bisa dikirim. Buat dokumen baru dari template ini lalu kirim dokumen tersebut.');
        }

        if (! $document->isDraft()) {
            $this->fail('Dokumen ini sudah dikirim.');
        }

        if ($document->signers->isEmpty()) {
            $this->fail('Tambahkan minimal satu penandatangan.');
        }

        foreach ($document->signers as $signer) {
            if (! $signer->fields->contains(fn ($field) => $field->field_type === FieldType::Signature || $field->field_type === FieldType::Initial)) {
                $this->fail("{$signer->name} belum punya kotak tanda tangan di dokumen.");
            }

            if ($viaEmail && ! $signer->is_owner && blank($signer->email)) {
                $this->fail("Email {$signer->name} wajib diisi untuk pengiriman otomatis lewat email.");
            }
        }

        DB::transaction(function () use ($document, $owner, $viaEmail) {
            $document->update([
                'status' => DocumentStatus::WaitingForSignatures,
                'send_via_email' => $viaEmail,
                'sent_at' => now(),
                'expires_at' => now()->addDays($document->expiry_days),
            ]);

            foreach ($document->signers as $signer) {
                $signer->issueAccessToken();
            }

            $document->record(AuditEvent::Sent, metadata: [
                'distribution' => $viaEmail ? 'email' : 'manual',
                'mode' => $document->signing_order_mode->value,
                'signers' => $document->signers->count(),
            ], userId: $owner->id);
        });

        $this->inviteActiveSigners($document->fresh());
        DocumentChanged::dispatch($document);
    }

    /**
     * Emails whoever's turn it is and has not been invited yet. Manual distribution only
     * marks the link as active; the owner shares it themselves.
     */
    public function inviteActiveSigners(Document $document): void
    {
        foreach ($document->activeSigners() as $signer) {
            if ($signer->invited_at !== null) {
                continue;
            }

            $signer->update(['invited_at' => now()]);

            if ($document->send_via_email && $signer->email && ! $signer->is_owner) {
                Notification::route('mail', $signer->email)->notify(new SignatureRequestNotification($signer));
            }
        }
    }

    /**
     * @throws ValidationException
     */
    public function remind(Signer $signer, ?User $owner = null): void
    {
        $document = $signer->document;

        if (! $document->status->isInProgress() || $signer->status->hasActed()) {
            $this->fail('Pengingat hanya bisa dikirim ke signer yang belum menandatangani.');
        }

        if (! $document->activeSigners()->contains('id', $signer->id)) {
            $this->fail("Belum giliran {$signer->name} untuk menandatangani.");
        }

        if (blank($signer->email) || $signer->is_owner) {
            $this->fail("{$signer->name} tidak punya email. Bagikan tautannya lewat WhatsApp.");
        }

        if ($owner !== null && $signer->last_reminded_at?->gt(now()->subHour())) {
            $this->fail('Pengingat terakhir baru dikirim kurang dari 1 jam lalu.');
        }

        $signer->update(['last_reminded_at' => now(), 'invited_at' => $signer->invited_at ?? now()]);
        Notification::route('mail', $signer->email)->notify(new SignatureRequestNotification($signer, isReminder: true));
        $document->record(AuditEvent::ReminderSent, $signer, ['trigger' => $owner ? 'manual' : 'scheduler'], $owner?->id);
    }

    /**
     * Fixes a typo in a pending signer's contact and issues a new link; the old link stops working.
     *
     * @param  array{name: string, email: ?string, phone: ?string}  $contact
     *
     * @throws ValidationException
     */
    public function updateContactAndResend(Signer $signer, array $contact, User $owner): void
    {
        $document = $signer->document;

        if (! $document->status->isInProgress() || $signer->status->hasActed()) {
            $this->fail('Kontak hanya bisa diubah untuk signer yang belum menandatangani.');
        }

        if ($document->send_via_email && ! $signer->is_owner && blank($contact['email'])) {
            $this->fail('Email wajib diisi karena dokumen dikirim lewat email.');
        }

        $before = $signer->only(['name', 'email', 'phone']);

        DB::transaction(function () use ($signer, $contact, $document, $before, $owner) {
            $signer->update([
                'name' => $contact['name'],
                'email' => $contact['email'] ?: null,
                'phone' => $contact['phone'] ?: null,
                'invited_at' => null,
            ]);
            $signer->issueAccessToken();

            $document->record(AuditEvent::ContactUpdated, $signer, ['before' => $before, 'after' => $signer->only(['name', 'email', 'phone'])], $owner->id);
            $document->record(AuditEvent::InvitationResent, $signer, userId: $owner->id);
        });

        $this->inviteActiveSigners($document->fresh());
        DocumentChanged::dispatch($document);
    }

    /**
     * @throws ValidationException
     */
    public function void(Document $document, User $owner, ?string $reason = null): void
    {
        if (! $document->status->isInProgress()) {
            $this->fail('Hanya dokumen yang sedang berjalan yang bisa dibatalkan.');
        }

        DB::transaction(function () use ($document, $owner, $reason) {
            $locked = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();

            if (! $locked->status->isInProgress()) {
                $this->fail('Status dokumen sudah berubah. Muat ulang halaman.');
            }

            $locked->update(['status' => DocumentStatus::Voided, 'voided_at' => now()]);
            $locked->record(AuditEvent::Voided, metadata: array_filter(['reason' => $reason]), userId: $owner->id);
        });

        $document->refresh();

        foreach ($document->signers as $signer) {
            if ($signer->invited_at && $signer->email && ! $signer->is_owner && ! $signer->status->hasActed() && $document->send_via_email) {
                Notification::route('mail', $signer->email)->notify(new DocumentVoidedNotification($signer));
            }
        }

        DocumentChanged::dispatch($document);
    }

    public function expire(Document $document): bool
    {
        $expired = DB::transaction(function () use ($document) {
            $locked = Document::query()->whereKey($document->id)->lockForUpdate()->first();

            // A document whose signatures are all in is only waiting for the queue; never expire it.
            if ($locked === null || ! $locked->status->isInProgress() || $locked->allSignersSigned()) {
                return false;
            }

            $locked->update(['status' => DocumentStatus::Expired]);
            $locked->record(AuditEvent::Expired, metadata: ['expires_at' => $locked->expires_at?->toIso8601String()]);

            return true;
        });

        if ($expired) {
            DocumentChanged::dispatch($document->refresh());
        }

        return $expired;
    }

    /**
     * @throws ValidationException
     */
    public function retrySealing(Document $document): void
    {
        if (! $document->isAwaitingSeal()) {
            $this->fail('Dokumen ini tidak sedang menunggu pemrosesan PDF.');
        }

        $document->update(['processing_failed_at' => null, 'processing_error' => null]);
        ProcessCompletedDocumentPdf::dispatch($document);
    }

    public function deleteDraft(Document $document): void
    {
        if (! $document->isDraft()) {
            $this->fail('Hanya draft yang bisa dihapus. Batalkan dokumen yang sudah dikirim.');
        }

        $this->storage->deleteDirectory("documents/{$document->id}");
        $document->delete();
    }

    /**
     * @throws ValidationException
     */
    public function delete(Document $document): void
    {
        if ($document->status->isInProgress()) {
            $this->fail('Dokumen yang sedang berjalan harus dibatalkan dulu sebelum dihapus.');
        }

        $this->storage->deleteDirectory("documents/{$document->id}");
        $document->delete();
    }

    /**
     * @throws ValidationException
     */
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['document' => $message]);
    }
}
