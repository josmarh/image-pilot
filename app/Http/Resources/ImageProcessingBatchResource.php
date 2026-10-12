<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImageProcessingBatchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $total = (int) $this->original_count;

        $completed = (int) $this->successful_count;
        $failed = (int) $this->failed_count;

        $processing = $this->items
            ->where('status', 'processing')
            ->count();

        $pending = $this->items
            ->where('status', 'pending')
            ->count();

        $processed = $completed + $failed;

        $percentage = $total > 0
            ? round(($processed / $total) * 100, 2)
            : 0;

        $originalBytes = (int) $this->original_size_bytes;
        $optimizedBytes = (int) $this->optimized_size_bytes;

        $savedBytes = max(
            0,
            $originalBytes - $optimizedBytes
        );

        $savedPercentage = $originalBytes > 0
            ? round(
                ($savedBytes / $originalBytes) * 100,
                2
            )
            : 0;

        return [
            'batch_reference' => $this->batch_reference,
            'status' => $this->status,

            'progress' => [
                'total' => $total,
                'completed' => $completed,
                'failed' => $failed,
                'pending' => $pending,
                'processing' => $processing,
                'percentage' => $percentage,
            ],

            'size' => [
                'original_bytes' => $originalBytes,
                'optimized_bytes' => $optimizedBytes,
                'saved_bytes' => $savedBytes,
                'saved_percentage' => $savedPercentage,
            ],

            'settings' => $this->settings,

            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
