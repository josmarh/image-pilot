<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImagePreset extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'width',
        'height',
        'resize_mode',
        'output_format',
        'quality',
        'remove_metadata',
        'watermark_enabled',
        'watermark_type',
        'watermark_settings',
        'is_system',
    ];

    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'quality' => 'integer',
            'remove_metadata' => 'boolean',
            'watermark_enabled' => 'boolean',
            'watermark_settings' => 'array',
            'is_system' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
