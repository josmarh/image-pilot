<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ImageProcessingBatch extends Model
{
    protected $fillable = [
        'user_id',
        'batch_reference',
        'guest_token_hash',
        'original_count',
        'successful_count',
        'failed_count',
        'original_size_bytes',
        'optimized_size_bytes',
        'status',
        'settings',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'original_count' => 'integer',
            'successful_count' => 'integer',
            'failed_count' => 'integer',
            'original_size_bytes' => 'integer',
            'optimized_size_bytes' => 'integer',
            'settings' => 'array',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ImageProcessingItem::class, 'batch_id');
    }
}
