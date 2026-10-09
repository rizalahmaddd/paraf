<?php

namespace App\Models;

use App\Enums\FieldType;
use Database\Factories\DocumentFieldFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentField extends Model
{
    /** @use HasFactory<DocumentFieldFactory> */
    use HasFactory, HasUuids;

    protected $fillable = [
        'document_id',
        'signer_id',
        'page_number',
        'x_ratio',
        'y_ratio',
        'width_ratio',
        'height_ratio',
        'field_type',
        'label',
        'is_required',
        'field_value',
        'filled_at',
    ];

    protected function casts(): array
    {
        return [
            'field_type' => FieldType::class,
            'page_number' => 'integer',
            'x_ratio' => 'float',
            'y_ratio' => 'float',
            'width_ratio' => 'float',
            'height_ratio' => 'float',
            'is_required' => 'boolean',
            'filled_at' => 'datetime',
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
     * @return BelongsTo<Signer, $this>
     */
    public function signer(): BelongsTo
    {
        return $this->belongsTo(Signer::class);
    }

    /**
     * Shape shared by the editor and the signing page.
     *
     * @return array<string, mixed>
     */
    public function toCanvasArray(): array
    {
        return [
            'id' => $this->id,
            'signer_id' => $this->signer_id,
            'page' => $this->page_number,
            'x' => $this->x_ratio,
            'y' => $this->y_ratio,
            'w' => $this->width_ratio,
            'h' => $this->height_ratio,
            'type' => $this->field_type->value,
            'label' => $this->label,
            'required' => $this->is_required,
        ];
    }
}
