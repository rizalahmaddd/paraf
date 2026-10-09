<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentPage extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'document_id',
        'page_number',
        'width_pt',
        'height_pt',
        'rotation_degrees',
        'thumbnail_path',
    ];

    protected function casts(): array
    {
        return [
            'page_number' => 'integer',
            'width_pt' => 'float',
            'height_pt' => 'float',
            'rotation_degrees' => 'integer',
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
     * Size as the reader sees it: a page with /Rotate 90 or 270 is shown sideways.
     *
     * @return array{width: float, height: float}
     */
    public function displaySize(): array
    {
        $isSideways = in_array($this->rotation_degrees % 360, [90, 270], true);

        return [
            'width' => $isSideways ? $this->height_pt : $this->width_pt,
            'height' => $isSideways ? $this->width_pt : $this->height_pt,
        ];
    }
}
