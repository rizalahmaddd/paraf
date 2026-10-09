<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Thin wrapper around resources/node/pdf-engine.cjs (pdf-lib). Node handles modern PDFs
 * (object streams, compressed xref) that the free PHP parsers cannot read.
 */
class PdfEngine
{
    /**
     * @return array{ok: bool, reason?: string, message?: string, features?: list<string>, pages?: list<array{width: float, height: float, rotation: int}>}
     */
    public function inspect(string $path): array
    {
        return $this->run('inspect', $path, 60);
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    public function bake(string $input, string $output, array $fields): void
    {
        $this->runJob('bake', ['input' => $input, 'output' => $output, 'fields' => $fields]);
    }

    public function append(string $base, string $appendix, string $output, ?string $title = null): void
    {
        $this->runJob('append', ['base' => $base, 'appendix' => $appendix, 'output' => $output, 'title' => $title]);
    }

    /**
     * @param  array<string, mixed>  $job
     */
    private function runJob(string $command, array $job): void
    {
        $jobFile = app(DocumentStorage::class)->temporaryPath('json');
        File::put($jobFile, json_encode($job, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

        try {
            $result = $this->run($command, $jobFile, 300);
        } finally {
            File::delete($jobFile);
        }

        if (! ($result['ok'] ?? false)) {
            throw new RuntimeException("PDF engine [{$command}] gagal: ".($result['message'] ?? 'tanpa pesan'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function run(string $command, string $argument, int $timeout): array
    {
        $result = Process::timeout($timeout)
            ->path(base_path())
            ->run([config('paraf.node_binary'), resource_path('node/pdf-engine.cjs'), $command, $argument]);

        if ($result->failed()) {
            throw new RuntimeException("PDF engine [{$command}] error: ".trim($result->errorOutput() ?: $result->output()));
        }

        $decoded = json_decode($result->output(), true);

        if (! is_array($decoded)) {
            throw new RuntimeException("PDF engine [{$command}] mengembalikan output yang tidak valid.");
        }

        return $decoded;
    }
}
