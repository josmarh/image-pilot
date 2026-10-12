<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ImagePresetResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,

            'settings' => [
                'width' => $this->width,
                'height' => $this->height,
                'resize_mode' => $this->resize_mode,
                'output_format' => $this->output_format,
                'quality' => $this->quality,
                'remove_metadata' => $this->remove_metadata,
                'watermark_enabled' => $this->watermark_enabled,
                'watermark_type' => $this->watermark_type,
                'watermark_settings' => $this->watermark_settings,
            ],

            'is_system' => $this->is_system,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
