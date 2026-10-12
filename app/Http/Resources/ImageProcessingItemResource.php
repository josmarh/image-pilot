<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImageProcessingItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $originalBytes = (int) $this->original_size_bytes;
        $outputBytes = (int) ($this->output_size_bytes ?? 0);

        $savedBytes = $outputBytes > 0
            ? max(0, $originalBytes - $outputBytes)
            : 0;

        $savedPercentage = $originalBytes > 0 && $outputBytes > 0
            ? round(($savedBytes / $originalBytes) * 100, 2)
            : 0;

        return [
            'id' => $this->id,

            'original' => [
                'filename' => $this->original_filename,
                'size_bytes' => $originalBytes,
                'width' => $this->original_width,
                'height' => $this->original_height,
                'format' => $this->original_format,
            ],

            'output' => [
                'filename' => $this->output_filename,
                'size_bytes' => $this->output_size_bytes,
                'width' => $this->output_width,
                'height' => $this->output_height,
                'format' => $this->output_format,
            ],

            'savings' => [
                'bytes' => $savedBytes,
                'percentage' => $savedPercentage,
            ],

            'status' => $this->status,
            'error_message' => $this->error_message,
        ];
    }
}
