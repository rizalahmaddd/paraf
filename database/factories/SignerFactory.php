<?php

namespace Database\Factories;

use App\Enums\SignerStatus;
use App\Models\Document;
use App\Models\Signer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Signer>
 */
class SignerFactory extends Factory
{
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => null,
            'color_tag' => '#2563EB',
            'signing_order' => 1,
            'is_owner' => false,
            'status' => SignerStatus::Pending,
        ];
    }

    public function signed(): static
    {
        return $this->state(fn () => [
            'status' => SignerStatus::Signed,
            'viewed_at' => now()->subMinute(),
            'signed_at' => now(),
            'signed_ip_address' => '127.0.0.1',
            'signed_user_agent' => 'PestPHP',
        ]);
    }
}
