<?php

namespace App\Services;

use App\Enums\AuditEvent;
use App\Enums\DocumentStatus;
use App\Enums\FieldType;
use App\Enums\SignerStatus;
use App\Events\DocumentChanged;
use App\Events\DocumentCompleted;
use App\Models\Document;
use App\Models\DocumentField;
use App\Models\Signer;
use App\Notifications\DocumentDeclinedNotification;
use App\Notifications\DocumentSignedNotification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Signer-side actions behind the zero-login magic link.
 */
class SigningService
{
    public const MAX_IMAGE_BYTES = 2 * 1024 * 1024;

    public function __construct(private DocumentStorage $storage, private DocumentWorkflow $workflow) {}

    public function sessionKey(Signer $signer): string
    {
        return 'paraf.passcode.'.$signer->id;
    }

    /**
     * Bound to the current token hash so a regenerated link asks for the passcode again.
     */
    public function hasVerifiedPasscode(Signer $signer, Request $request): bool
    {
        return ! $signer->hasPasscode()
            || hash_equals((string) $request->session()->get($this->sessionKey($signer)), (string) $signer->access_token_hash);
    }

    public function passcodeLockSeconds(Signer $signer): int
    {
        $key = $this->passcodeThrottleKey($signer);

        return RateLimiter::tooManyAttempts($key, config('paraf.passcode.max_attempts')) ? RateLimiter::availableIn($key) : 0;
    }

    /**
     * @throws ValidationException
     */
    public function verifyPasscode(Signer $signer, string $passcode, Request $request): void
    {
        $key = $this->passcodeThrottleKey($signer);
        $maxAttempts = config('paraf.passcode.max_attempts');

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            throw ValidationException::withMessages([
                'passcode' => 'Terlalu banyak percobaan. Coba lagi dalam '.ceil(RateLimiter::availableIn($key) / 60).' menit.',
            ]);
        }

        if (! Hash::check($passcode, (string) $signer->passcode_hash)) {
            RateLimiter::hit($key, config('paraf.passcode.lockout_seconds'));
            $signer->increment('passcode_attempts');
            $signer->document->record(AuditEvent::PasscodeFailed, $signer, ['attempt' => $signer->passcode_attempts]);

            $remaining = max(0, $maxAttempts - RateLimiter::attempts($key));

            throw ValidationException::withMessages([
                'passcode' => $remaining > 0
                    ? "Passcode salah. Sisa {$remaining} percobaan."
                    : 'Passcode salah 5 kali. Akses dikunci selama 15 menit.',
            ]);
        }

        RateLimiter::clear($key);
        $signer->update(['passcode_attempts' => 0]);
        $request->session()->put($this->sessionKey($signer), $signer->access_token_hash);
        $signer->document->record(AuditEvent::PasscodeVerified, $signer);
    }

    public function markViewed(Signer $signer): void
    {
        if ($signer->viewed_at !== null) {
            return;
        }

        $signer->update([
            'viewed_at' => now(),
            'status' => $signer->status === SignerStatus::Pending ? SignerStatus::Viewed : $signer->status,
        ]);
        $signer->document->record(AuditEvent::Viewed, $signer);
        DocumentChanged::dispatch($signer->document);
    }

    public function isTurnOf(Signer $signer): bool
    {
        return $signer->document->activeSigners()->contains('id', $signer->id);
    }

    /**
     * Validates and stores every field of the signer in one transaction. The document row is
     * locked so two parallel signers finishing at the same second cannot both miss (or both
     * trigger) the "last signer" check.
     *
     * @param  array<string, mixed>  $values  field id => value (PNG data URL for signature/initial)
     *
     * @throws AuthorizationException|ValidationException
     */
    public function sign(Signer $signer, array $values, Request $request): Document
    {
        $document = $signer->document;
        $fields = $signer->fields()->get()->keyBy('id');

        $foreign = array_diff(array_keys($values), $fields->keys()->all());
        if ($foreign !== []) {
            throw new AuthorizationException('Anda hanya boleh mengisi kotak milik Anda sendiri.');
        }

        $this->assertSignable($signer);

        $prepared = $this->prepareValues($signer, $fields->all(), $values);
        $storedImages = [];

        try {
            foreach ($prepared as $fieldId => $value) {
                if ($fields[$fieldId]->field_type->isImage()) {
                    // Unique per attempt: a losing parallel submit cleans up its own files, not the winner's.
                    $path = "documents/{$document->id}/signatures/{$fieldId}-".Str::random(12).'.png';
                    $this->storage->put($path, $value);
                    $prepared[$fieldId] = $storedImages[] = $path;
                }
            }

            $isLastSigner = DB::transaction(function () use ($signer, $document, $fields, $prepared, $request) {
                $lockedDocument = Document::query()->whereKey($document->id)->lockForUpdate()->firstOrFail();
                $lockedSigner = Signer::query()->whereKey($signer->id)->lockForUpdate()->firstOrFail();
                $lockedSigner->setRelation('document', $lockedDocument);

                $this->assertSignable($lockedSigner);

                $now = now();
                foreach ($fields as $field) {
                    $field->update([
                        'field_value' => $prepared[$field->id] ?? null,
                        'filled_at' => array_key_exists($field->id, $prepared) ? $now : null,
                    ]);
                }

                $lockedSigner->update([
                    'status' => SignerStatus::Signed,
                    'signed_at' => $now,
                    'viewed_at' => $lockedSigner->viewed_at ?? $now,
                    'signed_ip_address' => $request->ip(),
                    'signed_user_agent' => mb_substr((string) $request->userAgent(), 0, 1000),
                ]);

                $lockedDocument->record(AuditEvent::Signed, $lockedSigner, [
                    'fields' => count($prepared),
                ]);

                $isLast = $lockedDocument->signers()->where('status', '!=', SignerStatus::Signed->value)->doesntExist();

                if (! $isLast && $lockedDocument->status === DocumentStatus::WaitingForSignatures) {
                    $lockedDocument->update(['status' => DocumentStatus::PartiallySigned]);
                }

                return $isLast;
            });
        } catch (Throwable $e) {
            foreach ($storedImages as $path) {
                $this->storage->delete($path);
            }

            throw $e;
        }

        $document->refresh();
        $signer->refresh();

        if ($isLastSigner) {
            DocumentCompleted::dispatch($document);
        } else {
            $this->workflow->inviteActiveSigners($document);

            $nextSignerNames = $document->isSequential() && ! $document->send_via_email
                ? $document->activeSigners()->reject(fn (Signer $next) => $next->is_owner)->pluck('name')->all()
                : [];

            if (! $signer->is_owner) {
                $document->user->notify(new DocumentSignedNotification($signer, $nextSignerNames));
            }
        }

        DocumentChanged::dispatch($document);

        return $document;
    }

    /**
     * @throws ValidationException
     */
    public function decline(Signer $signer, string $reason): void
    {
        DB::transaction(function () use ($signer, $reason) {
            $document = Document::query()->whereKey($signer->document_id)->lockForUpdate()->firstOrFail();
            $signer->setRelation('document', $document);

            $this->assertSignable($signer);

            $signer->update(['status' => SignerStatus::Declined, 'decline_reason' => $reason]);
            $document->update(['status' => DocumentStatus::Declined]);
            $document->record(AuditEvent::Declined, $signer, ['reason' => $reason]);
        });

        $signer->refresh();

        if (! $signer->is_owner) {
            $signer->document->user->notify(new DocumentDeclinedNotification($signer));
        }

        DocumentChanged::dispatch($signer->document);
    }

    /**
     * @throws ValidationException
     */
    private function assertSignable(Signer $signer): void
    {
        $document = $signer->document;

        if (! $document->status->isInProgress()) {
            $this->fail('Dokumen ini sudah tidak bisa ditandatangani ('.mb_strtolower($document->status->label()).').');
        }

        if ($document->expires_at?->isPast()) {
            $this->fail('Batas waktu penandatanganan dokumen ini sudah lewat.');
        }

        if ($signer->status->hasActed()) {
            $this->fail('Anda sudah merespons dokumen ini.');
        }

        if (! $this->isTurnOf($signer)) {
            $this->fail('Belum giliran Anda untuk menandatangani dokumen ini.');
        }
    }

    /**
     * @param  array<string, DocumentField>  $fields
     * @param  array<string, mixed>  $values
     * @return array<string, string> field id => value (raw PNG bytes for image fields)
     *
     * @throws ValidationException
     */
    private function prepareValues(Signer $signer, array $fields, array $values): array
    {
        $prepared = [];
        $errors = [];

        foreach ($fields as $id => $field) {
            $raw = $values[$id] ?? null;

            $value = match ($field->field_type) {
                FieldType::Date => now()->timezone('Asia/Jakarta')->locale('id')->isoFormat('D MMMM YYYY'),
                FieldType::Name => $signer->name,
                FieldType::Signature, FieldType::Initial => is_string($raw) && $raw !== '' ? $this->decodePng($raw) : null,
                FieldType::Checkbox => filter_var($raw, FILTER_VALIDATE_BOOL) ? '1' : null,
                FieldType::Text => is_scalar($raw) && trim((string) $raw) !== '' ? mb_substr(trim((string) $raw), 0, 255) : null,
            };

            if ($value === false) {
                $errors["fields.{$id}"] = 'Gambar tanda tangan tidak valid.';

                continue;
            }

            if ($value === null) {
                if ($field->is_required || $field->field_type->isImage()) {
                    $errors["fields.{$id}"] = ($field->label ?: $field->field_type->label()).' wajib diisi.';
                }

                continue;
            }

            $prepared[$id] = $value;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $prepared;
    }

    public function decodePng(string $dataUrl): string|false
    {
        if (! str_starts_with($dataUrl, 'data:image/png;base64,')) {
            return false;
        }

        $binary = base64_decode(substr($dataUrl, 22), true);

        if ($binary === false || strlen($binary) > self::MAX_IMAGE_BYTES) {
            return false;
        }

        $info = @getimagesizefromstring($binary);

        if ($info === false || $info[2] !== IMAGETYPE_PNG || $info[0] > 3000 || $info[1] > 3000) {
            return false;
        }

        return $binary;
    }

    private function passcodeThrottleKey(Signer $signer): string
    {
        return 'paraf-passcode:'.$signer->id;
    }

    /**
     * @throws ValidationException
     */
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['document' => $message]);
    }
}
