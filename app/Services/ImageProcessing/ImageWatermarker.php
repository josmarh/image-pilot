<?php

namespace App\Services\ImageProcessing;

use GdImage;
use RuntimeException;

class ImageWatermarker
{
    private const POSITIONS = [
        'top-left',
        'top-center',
        'top-right',
        'center',
        'bottom-left',
        'bottom-center',
        'bottom-right',
    ];

    /**
     * Apply a text watermark to an image.
     *
     * @param array{
     *     text?: string,
     *     position?: string,
     *     opacity?: int,
     *     size?: int,
     *     color?: string,
     *     margin?: int
     * } $settings
     */
    public function apply(
        GdImage $image,
        array $settings
    ): void {
        $text = trim((string) ($settings['text'] ?? ''));

        if ($text === '') {
            throw new RuntimeException(
                'Watermark text is required.'
            );
        }

        if (!function_exists('imagettftext')) {
            throw new RuntimeException(
                'Text watermarking requires GD FreeType support.'
            );
        }

        $position = $settings['position'] ?? 'bottom-right';
        $opacity = (int) ($settings['opacity'] ?? 70);
        $size = (int) ($settings['size'] ?? 24);
        $margin = (int) ($settings['margin'] ?? 20);
        $color = strtoupper((string) ($settings['color'] ?? '#FFFFFF'));

        if (!in_array($position, self::POSITIONS, true)) {
            throw new RuntimeException(
                'Invalid watermark position.'
            );
        }

        if ($opacity < 0 || $opacity > 100) {
            throw new RuntimeException(
                'Watermark opacity must be between 0 and 100.'
            );
        }

        if ($size < 8 || $size > 200) {
            throw new RuntimeException(
                'Watermark size must be between 8 and 200.'
            );
        }

        if ($margin < 0 || $margin > 1000) {
            throw new RuntimeException(
                'Watermark margin must be between 0 and 1000.'
            );
        }

        if (!preg_match('/^#[0-9A-F]{6}$/', $color)) {
            throw new RuntimeException(
                'Watermark color must be a valid hex color.'
            );
        }

        // Use a bundled font so the service does not depend on
        // a font installed at a particular server path.
        $fontPath = resource_path('fonts/DejaVuSans.ttf');

        if (!is_file($fontPath) || !is_readable($fontPath)) {
            throw new RuntimeException(
                "Watermark font file is missing or unreadable: {$fontPath}"
            );
        }

        $red = hexdec(substr($color, 1, 2));
        $green = hexdec(substr($color, 3, 2));
        $blue = hexdec(substr($color, 5, 2));

        // GD alpha: 0 = opaque, 127 = fully transparent.
        $alpha = 127 - (int) round($opacity * 127 / 100);

        $textColor = imagecolorallocatealpha(
            $image,
            $red,
            $green,
            $blue,
            $alpha
        );

        if ($textColor === false) {
            throw new RuntimeException(
                'Unable to allocate the watermark color.'
            );
        }

        $fontSize = $size;
        $angle = 0;

        $bounds = imagettfbbox(
            $fontSize,
            $angle,
            $fontPath,
            $text
        );

        if ($bounds === false) {
            throw new RuntimeException(
                'Unable to calculate watermark text dimensions.'
            );
        }

        $textWidth = abs($bounds[4] - $bounds[0]);
        $textHeight = abs($bounds[5] - $bounds[1]);

        $imageWidth = imagesx($image);
        $imageHeight = imagesy($image);

        $x = match ($position) {
            'top-left', 'bottom-left' =>
                $margin,

            'top-center', 'center', 'bottom-center' =>
                (int) round(($imageWidth - $textWidth) / 2),

            'top-right', 'bottom-right' =>
                $imageWidth - $textWidth - $margin,
        };

        $y = match ($position) {
            'top-left', 'top-center', 'top-right' =>
                $margin + $textHeight,

            'center' =>
                (int) round(($imageHeight + $textHeight) / 2),

            'bottom-left', 'bottom-center', 'bottom-right' =>
                $imageHeight - $margin,
        };

        // Avoid negative coordinates for small images.
        $x = max(0, $x);
        $y = min($imageHeight, max($textHeight, $y));

        // Enable blending so watermark opacity is respected.
        imagealphablending($image, true);

        $result = imagettftext(
            $image,
            $fontSize,
            $angle,
            $x,
            $y,
            $textColor,
            $fontPath,
            $text
        );

        if ($result === false) {
            throw new RuntimeException(
                'Failed to draw the text watermark.'
            );
        }
    }
}