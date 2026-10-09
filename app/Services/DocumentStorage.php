<?php

namespace App\Services;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Private storage for PDFs, thumbnails and signature images. Contents are sealed with
 * libsodium secretbox before they reach the disk (local, S3 or R2).
 */
class DocumentStorage
{
    private const MAGIC = 'PRF1';

    public function disk(): Filesystem
    {
        return Storage::disk(config('paraf.disk'));
    }

    public function put(string $path, string $contents): string
    {
        $this->disk()->put($path, $this->seal($contents));

        return $path;
    }

    public function get(string $path): string
    {
        $raw = $this->disk()->get($path);

        if ($raw === null) {
            throw new RuntimeException("File dokumen [{$path}] tidak ditemukan di storage.");
        }

        return $this->open($raw);
    }

    public function exists(?string $path): bool
    {
        return $path !== null && $this->disk()->exists($path);
    }

    public function delete(?string $path): void
    {
        if ($path !== null) {
            $this->disk()->delete($path);
        }
    }

    public function deleteDirectory(string $directory): void
    {
        $this->disk()->deleteDirectory($directory);
    }

    /**
     * Decrypted copy on local disk for tools that need a real path (the Node PDF engine).
     * The caller must unlink it.
     */
    public function toTemporaryFile(string $path, string $extension = 'pdf'): string
    {
        $file = $this->temporaryPath($extension);
        file_put_contents($file, $this->get($path));

        return $file;
    }

    public function temporaryPath(string $extension = 'pdf'): string
    {
        $directory = storage_path('app/paraf-tmp');

        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }

        return $directory.'/'.bin2hex(random_bytes(16)).'.'.$extension;
    }

    private function seal(string $contents): string
    {
        if (! config('paraf.encrypt_files')) {
            return $contents;
        }

        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        return self::MAGIC.$nonce.sodium_crypto_secretbox($contents, $nonce, $this->key());
    }

    private function open(string $raw): string
    {
        // Files written while encryption was off stay readable after turning it on.
        if (! str_starts_with($raw, self::MAGIC)) {
            return $raw;
        }

        $offset = strlen(self::MAGIC);
        $nonce = substr($raw, $offset, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, $offset + SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $this->key());

        if ($plain === false) {
            throw new RuntimeException('File dokumen tidak bisa didekripsi. Apakah APP_KEY berubah?');
        }

        return $plain;
    }

    private function key(): string
    {
        $appKey = (string) config('app.key');

        if (str_starts_with($appKey, 'base64:')) {
            $appKey = base64_decode(substr($appKey, 7));
        }

        return hash_hmac('sha256', 'paraf-document-storage', $appKey, true);
    }
}
