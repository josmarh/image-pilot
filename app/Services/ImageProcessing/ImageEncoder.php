<?php

namespace App\Services\ImageProcessing;

use GdImage;
use RuntimeException;

class ImageEncoder
{
    /**
     * Supported output formats.
     */
    private const SUPPORTED_FORMATS = [
        'jpg',
        'jpeg',
        'png',
        'webp',
    ];

    /**
     * Encode and save an image.
     *
     * @return array{
     *     path: string,
     *     format: string,
     *     size_bytes: int,
     *     size_kb: float,
     *     size_mb: float
     * }
     */
    public function encode(
        GdImage $image,
        string $outputPath,
        string $format,
        int $quality = 80
    ): array {
        $format = strtolower($format);

        if (!in_array($format, self::SUPPORTED_FORMATS, true)) {
            throw new RuntimeException(
                'Unsupported output format. Use JPG, PNG, or WebP.'
            );
        }

        // Normalize JPEG extensions.
        if ($format === 'jpeg') {
            $format = 'jpg';
        }

        // Validate quality settings.
        if ($quality < 0 || $quality > 100) {
            throw new RuntimeException(
                'Quality must be between 0 and 100.'
            );
        }

        // Ensure the destination directory exists.
        $directory = dirname($outputPath);

        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true) && !is_dir($directory)) {
                throw new RuntimeException(
                    'Unable to create the output directory.'
                );
            }
        }

        if (!is_writable($directory)) {
            throw new RuntimeException(
                'The output directory is not writable.'
            );
        }

        // Encode using the selected format.
        $success = match ($format) {
            'jpg' => $this->encodeJpeg(
                $image,
                $outputPath,
                $quality
            ),

            'png' => $this->encodePng(
                $image,
                $outputPath,
                $quality
            ),

            'webp' => $this->encodeWebp(
                $image,
                $outputPath,
                $quality
            ),
        };

        if (!$success || !is_file($outputPath)) {
            throw new RuntimeException(
                'Failed to encode the image.'
            );
        }

        $size = filesize($outputPath);

        if ($size === false || $size === 0) {
            throw new RuntimeException(
                'The encoded image is empty or unreadable.'
            );
        }

        return [
            'path' => $outputPath,
            'format' => $format,
            'size_bytes' => $size,
            'size_kb' => round($size / 1024, 2),
            'size_mb' => round($size / 1048576, 2),
        ];
    }

    /**
     * Encode JPEG.
     */
    private function encodeJpeg(
        GdImage $image,
        string $path,
        int $quality
    ): bool {
        if (!function_exists('imagejpeg')) {
            throw new RuntimeException(
                'JPEG encoding is not supported by this GD installation.'
            );
        }

        return imagejpeg($image, $path, $quality);
    }

    /**
     * Encode PNG.
     *
     * PNG uses compression levels from 0 to 9.
     * The supplied quality setting is mapped to this range.
     */
    private function encodePng(
        GdImage $image,
        string $path,
        int $quality
    ): bool {
        if (!function_exists('imagepng')) {
            throw new RuntimeException(
                'PNG encoding is not supported by this GD installation.'
            );
        }

        $compression = (int) round(
            9 - ($quality / 100 * 9)
        );

        // Preserve alpha transparency.
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return imagepng(
            $image,
            $path,
            $compression
        );
    }

    /**
     * Encode WebP.
     */
    private function encodeWebp(
        GdImage $image,
        string $path,
        int $quality
    ): bool {
        if (!function_exists('imagewebp')) {
            throw new RuntimeException(
                'WebP encoding is not supported by this GD installation.'
            );
        }

        return imagewebp(
            $image,
            $path,
            $quality
        );
    }
}