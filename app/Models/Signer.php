<?php

namespace App\Models;

use App\Enums\SignerStatus;
use Database\Factories\SignerFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;

class Signer extends Model
{
    /** @use HasFactory<SignerFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'document_id',
        'name',
        'email',
        'phone',
        'passcode_hash',
        'passcode_attempts',
        'color_tag',
        'signing_order',
        'is_owner',
        'status',
        'decline_reason',
        'invited_at',
        'last_reminded_at',
        'viewed_at',
        'signed_at',
        'signed_ip_address',
        'signed_user_agent',
    ];

    protected $hidden = [
        'access_token_hash',
        'access_token_encrypted',
        'passcode_hash',
    ];

    protected function casts(): array
    {
        return [
            'status' => SignerStatus::class,
            'is_owner' => 'boolean',
            'signing_order' => 'integer',
            'passcode_attempts' => 'integer',
            'invited_at' => 'datetime',
            'last_reminded_at' => 'datetime',
            'viewed_at' => 'datetime',
            'signed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * @return HasMany<DocumentField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(DocumentField::class);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function findByToken(string $token): ?self
    {
        if (strlen($token) !== 64 || ! ctype_alnum($token)) {
            return null;
        }

        return self::query()->where('access_token_hash', self::hashToken($token))->first();
    }

    /**
     * Issues a fresh link and invalidates the previous one. The plain token is kept encrypted
     * so the owner can copy the link again later; lookups only ever use the hash.
     */
    public function issueAccessToken(): string
    {
        $token = Str::random(64);

        $this->forceFill([
            'access_token_hash' => self::hashToken($token),
            'access_token_encrypted' => Crypt::encryptString($token),
        ])->save();

        return $token;
    }

    public function accessToken(): ?string
    {
        return $this->access_token_encrypted ? Crypt::decryptString($this->access_token_encrypted) : null;
    }

    public function signingUrl(): ?string
    {
        $token = $this->accessToken();

        return $token ? route('sign.show', $token) : null;
    }

    public function hasPasscode(): bool
    {
        return $this->passcode_hash !== null;
    }

    public function whatsappMessage(): ?string
    {
        $url = $this->signingUrl();

        if ($url === null) {
            return null;
        }

        return "Halo {$this->name}, silakan tinjau dan tanda tangani dokumen '{$this->document->title}' melalui tautan aman berikut: {$url}. Terima kasih!";
    }

    /**
     * wa.me link with the message prefilled; null when no usable phone number is stored.
     */
    public function whatsappUrl(): ?string
    {
        $message = $this->whatsappMessage();
        $digits = preg_replace('/\D+/', '', (string) $this->phone);

        if ($message === null || $digits === '') {
            return null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = '62'.substr($digits, 1);
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($message);
    }

    public function contactLabel(): string
    {
        return collect([$this->email, $this->phone])->filter()->implode(' · ') ?: '-';
    }

    public function initials(): string
    {
        return Str::of($this->name)->explode(' ')->filter()->take(2)
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))->implode('');
    }
}
