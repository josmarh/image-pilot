<?php

namespace Database\Seeders;

use App\Models\ImagePreset;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ImagePresetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $presets = [
            [
                'name' => 'Blog Images',
                'description' => 'Prepare website blog and article images.',
                'width' => 1200,
                'height' => 1200,
                'resize_mode' => 'contain',
                'output_format' => 'webp',
                'quality' => 80,
            ],
            [
                'name' => 'Product Images',
                'description' => 'Optimize product images for online stores.',
                'width' => 1600,
                'height' => 1600,
                'resize_mode' => 'contain',
                'output_format' => 'webp',
                'quality' => 85,
            ],
            [
                'name' => 'Thumbnails',
                'description' => 'Create compact, consistently sized thumbnails.',
                'width' => 400,
                'height' => 400,
                'resize_mode' => 'cover',
                'output_format' => 'webp',
                'quality' => 75,
            ],
        ];

        foreach ($presets as $preset) {
            ImagePreset::updateOrCreate(
                [
                    'user_id' => null,
                    'is_system' => true,
                    'name' => $preset['name'],
                ],
                [
                    ...$preset,
                    'remove_metadata' => true,
                    'watermark_enabled' => false,
                    'watermark_type' => null,
                    'watermark_settings' => null,
                ],
            );
        }
    }
}
