<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Services\DocumentStorage;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\Rule;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'username', 'email', 'phone', 'password', 'saved_signature_path', 'saved_initial_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use Auditable;

    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Username disimpan huruf kecil supaya login tidak peka huruf besar/kecil.
     */
    protected function username(): Attribute
    {
        return Attribute::set(fn (?string $value) => $value === null ? null : strtolower(trim($value)));
    }

    /**
     * Nomor HP disimpan dalam satu bentuk baku (lihat normalizePhone()) supaya "0812-3456",
     * "+62812 3456", dan "628123456" dianggap nomor yang sama saat login maupun cek unik.
     */
    protected function phone(): Attribute
    {
        return Attribute::set(fn (?string $value) => blank($value) ? null : self::normalizePhone($value));
    }

    /**
     * Ubah nomor HP Indonesia ke bentuk baku berawalan 0 dan hanya berisi angka.
     */
    public static function normalizePhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone);

        return str_starts_with($digits, '62') ? '0'.substr($digits, 2) : $digits;
    }

    /**
     * Username wajib diawali huruf supaya tidak tertukar dengan nomor HP di form login.
     *
     * @return array<int, mixed>
     */
    public static function usernameRules(?self $ignore = null): array
    {
        return ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-z][a-z0-9._]*$/', Rule::unique(self::class)->ignore($ignore?->id)];
    }

    /**
     * Dipanggil setelah nomor dinormalisasi (normalizePhone()), jadi cek unik membandingkan bentuk baku.
     *
     * @return array<int, mixed>
     */
    public static function phoneRules(?self $ignore = null): array
    {
        return ['nullable', 'string', 'regex:/^0\d{8,14}$/', Rule::unique(self::class)->ignore($ignore?->id)];
    }

    /**
     * @return array<string, string>
     */
    public static function identityValidationMessages(): array
    {
        return [
            'username.regex' => 'Username hanya boleh huruf kecil, angka, titik, dan garis bawah, diawali huruf.',
            'username.unique' => 'Username ini sudah dipakai.',
            'phone.regex' => 'Nomor HP tidak valid. Contoh: 081234567890.',
            'phone.unique' => 'Nomor HP ini sudah dipakai akun lain.',
        ];
    }

    /**
     * @return HasMany<Document, $this>
     */
    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    /**
     * Apakah akun merupakan Superadmin yang memiliki bypass akses penuh ke semua modul.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('superadmin');
    }

    public function savedSignatureBase64(): ?string
    {
        if (! $this->saved_signature_path) {
            return null;
        }

        try {
            $storage = app(DocumentStorage::class);
            if (! $storage->exists($this->saved_signature_path)) {
                return null;
            }

            return 'data:image/png;base64,'.base64_encode($storage->get($this->saved_signature_path));
        } catch (\Throwable) {
            return null;
        }
    }

    public function savedInitialBase64(): ?string
    {
        if (! $this->saved_initial_path) {
            return null;
        }

        try {
            $storage = app(DocumentStorage::class);
            if (! $storage->exists($this->saved_initial_path)) {
                return null;
            }

            return 'data:image/png;base64,'.base64_encode($storage->get($this->saved_initial_path));
        } catch (\Throwable) {
            return null;
        }
    }
}
