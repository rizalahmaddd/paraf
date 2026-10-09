<?php

namespace App\Models;

use App\Enums\AuditEvent;
use App\Enums\DocumentStatus;
use App\Enums\SignerStatus;
use App\Enums\SigningOrderMode;
use Database\Factories\DocumentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Document extends Model
{
    /** @use HasFactory<DocumentFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'title',
        'description',
        'status',
        'signing_order_mode',
        'send_via_email',
        'is_template',
        'original_filename',
        'original_pdf_path',
        'completed_pdf_path',
        'thumbnail_path',
        'original_hash_sha256',
        'signed_hash_sha256',
        'completed_hash_sha256',
        'file_size',
        'total_pages',
        'expiry_days',
        'expires_at',
        'sent_at',
        'completed_at',
        'voided_at',
        'processing_failed_at',
        'processing_error',
    ];

    protected function casts(): array
    {
        return [
            'status' => DocumentStatus::class,
            'is_template' => 'boolean',
            'signing_order_mode' => SigningOrderMode::class,
            'send_via_email' => 'boolean',
            'file_size' => 'integer',
            'total_pages' => 'integer',
            'expiry_days' => 'integer',
            'expires_at' => 'datetime',
            'sent_at' => 'datetime',
            'completed_at' => 'datetime',
            'voided_at' => 'datetime',
            'processing_failed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<Signer, $this>
     */
    public function signers(): HasMany
    {
        return $this->hasMany(Signer::class)->orderBy('signing_order')->orderBy('created_at');
    }

    /**
     * @return HasMany<DocumentPage, $this>
     */
    public function pages(): HasMany
    {
        return $this->hasMany(DocumentPage::class)->orderBy('page_number');
    }

    /**
     * @return HasMany<DocumentField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(DocumentField::class);
    }

    /**
     * @return HasMany<AuditLog, $this>
     */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class)->orderBy('created_at')->orderBy('id');
    }

    /**
     * @param  Builder<Document>  $query
     */
    public function scopeOwnedBy(Builder $query, User $user): void
    {
        $query->where('user_id', $user->id);
    }

    /**
     * @param  Builder<Document>  $query
     */
    public function scopeTemplates(Builder $query): void
    {
        $query->where('is_template', true);
    }

    /**
     * @param  Builder<Document>  $query
     */
    public function scopeNotTemplates(Builder $query): void
    {
        $query->where('is_template', false);
    }

    public function isTemplate(): bool
    {
        return (bool) $this->is_template;
    }

    public function isDraft(): bool
    {
        return $this->status === DocumentStatus::Draft;
    }

    public function isSequential(): bool
    {
        return $this->signing_order_mode === SigningOrderMode::Sequential;
    }

    public function allSignersSigned(): bool
    {
        $signers = $this->relationLoaded('signers') ? $this->signers : $this->signers()->get();

        return $signers->isNotEmpty() && $signers->every(fn (Signer $signer) => $signer->status === SignerStatus::Signed);
    }

    /**
     * Every signature is in but the sealed PDF has not been produced yet (queued or failed).
     */
    public function isAwaitingSeal(): bool
    {
        return $this->status->isInProgress() && $this->allSignersSigned();
    }

    /**
     * Signers whose link is active right now. In sequential mode only the lowest pending
     * order is active; in parallel mode everyone who has not acted yet.
     *
     * @return Collection<int, Signer>
     */
    public function activeSigners(): Collection
    {
        $pending = $this->signers()->get()->reject(fn (Signer $signer) => $signer->status->hasActed())->values();

        if (! $this->isSequential() || $pending->isEmpty()) {
            return $pending;
        }

        $lowestOrder = $pending->min('signing_order');

        return $pending->where('signing_order', $lowestOrder)->values();
    }

    public function record(AuditEvent $event, ?Signer $signer = null, array $metadata = [], ?int $userId = null): AuditLog
    {
        $request = app()->runningInConsole() ? null : request();

        return $this->auditLogs()->create([
            'signer_id' => $signer?->id,
            'user_id' => $userId,
            'event_type' => $event,
            'ip_address' => $request?->ip(),
            'user_agent' => $request ? mb_substr((string) $request->userAgent(), 0, 1000) : null,
            'metadata' => $metadata ?: null,
        ]);
    }
}
