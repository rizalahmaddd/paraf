<?php

namespace Database\Factories;

use App\Enums\FieldType;
use App\Models\DocumentField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentField>
 */
class DocumentFieldFactory extends Factory
{
    public function definition(): array
    {
        return [
            'page_number' => 1,
            'x_ratio' => 0.1,
            'y_ratio' => 0.7,
            'width_ratio' => 0.3,
            'height_ratio' => 0.08,
            'field_type' => FieldType::Signature,
            'label' => null,
            'is_required' => true,
        ];
    }

    public function type(FieldType $type, bool $required = true): static
    {
        return $this->state(fn () => ['field_type' => $type, 'is_required' => $required]);
    }
}
