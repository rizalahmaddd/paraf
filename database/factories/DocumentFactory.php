<?php

namespace Database\Factories;

use App\Enums\DocumentStatus;
use App\Enums\SigningOrderMode;
use App\Models\Document;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => 'Perjanjian '.fake()->words(2, true),
            'description' => null,
            'status' => DocumentStatus::Draft,
            'signing_order_mode' => SigningOrderMode::Parallel,
            'send_via_email' => true,
            'original_filename' => 'perjanjian.pdf',
            'original_pdf_path' => 'documents/missing/original.pdf',
            'original_hash_sha256' => hash('sha256', fake()->uuid()),
            'file_size' => 1024,
            'total_pages' => 1,
            'expiry_days' => 14,
        ];
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => DocumentStatus::WaitingForSignatures,
            'sent_at' => now(),
            'expires_at' => now()->addDays(14),
        ]);
    }

    public function sequential(): static
    {
        return $this->state(fn () => ['signing_order_mode' => SigningOrderMode::Sequential]);
    }
}
