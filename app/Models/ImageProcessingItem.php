<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImageProcessingItem extends Model
{
    protected $fillable = [
        'batch_id',
        'original_filename',
        'original_path',
        'original_size_bytes',
        'original_width',
        'original_height',
        'original_format',
        'output_filename',
        'output_path',
        'output_size_bytes',
        'output_width',
        'output_height',
        'output_format',
        'status',
        'error_message',
        'claim_token',
        'claimed_at',
        'attempts',
        'available_at',
    ];

    protected function casts(): array
    {
        return [
            'original_size_bytes' => 'integer',
            'output_size_bytes' => 'integer',
            'original_width' => 'integer',
            'original_height' => 'integer',
            'output_width' => 'integer',
            'output_height' => 'integer',
            'claimed_at' => 'datetime',
            'available_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(
            ImageProcessingBatch::class,
            'batch_id'
        );
    }
}
