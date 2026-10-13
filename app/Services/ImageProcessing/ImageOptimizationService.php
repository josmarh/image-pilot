<?php

namespace App\Services\ImageProcessing;

use GdImage;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

class ImageOptimizationService
{
    public function __construct(
        protected ImageInspector $inspector,
        protected ImageProcessor $processor,
        protected ImageResizer $resizer,
        protected ImageEncoder $encoder,
        protected ImageWatermarker $watermarker,
    ) {}

    /**
     * Optimize an image by optionally resizing it and encoding
     * it in the requested output format.
     *
     * @throws Throwable
     */
    public function optimize(
        string $sourcePath,
        string $outputPath,
        array $settings = []
    ): array {
        $original = $this->inspector->inspect($sourcePath);

        $outputFormat = strtolower(
            $settings['output_format'] ?? $original['format']
        );

        if ($outputFormat === 'jpeg') {
            $outputFormat = 'jpg';
        }

        if (!in_array($outputFormat, ['jpg', 'png', 'webp'], true)) {
            throw new InvalidArgumentException(
                'Unsupported output format.'
            );
        }

        $quality = (int) ($settings['quality'] ?? 80);

        if ($quality < 0 || $quality > 100) {
            throw new InvalidArgumentException(
                'Quality must be between 0 and 100.'
            );
        }

        $width = isset($settings['width'])
            ? (int) $settings['width']
            : null;

        $height = isset($settings['height'])
            ? (int) $settings['height']
            : null;

        $resizeMode = strtolower(
            $settings['resize_mode'] ?? 'contain'
        );

        $backgroundColor = $settings['background_color']
            ?? '#FFFFFF';

        if (
            ($width !== null && $width <= 0) ||
            ($height !== null && $height <= 0)
        ) {
            throw new InvalidArgumentException(
                'Width and height must be positive integers.'
            );
        }

        if (
            ($width !== null && $width > 12000) ||
            ($height !== null && $height > 12000)
        ) {
            throw new InvalidArgumentException(
                'Width and height cannot exceed 12000 pixels.'
            );
        }

        if (!in_array(
            $resizeMode,
            ['contain', 'cover', 'stretch'],
            true
        )) {
            throw new InvalidArgumentException(
                'Unsupported resize mode.'
            );
        }

        // Watermark setup
        $watermarkEnabled = filter_var(
            $settings['watermark_enabled'] ?? false,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );

        if ($watermarkEnabled === null) {
            throw new InvalidArgumentException(
                'Watermark enabled must be a boolean.'
            );
        }

        $watermarkSettings = [
            'text' => trim((string) ($settings['watermark_text'] ?? '')),
            'position' => $settings['watermark_position'] ?? 'bottom-right',
            'opacity' => (int) ($settings['watermark_opacity'] ?? 70),
            'size' => (int) ($settings['watermark_size'] ?? 24),
            'color' => $settings['watermark_color'] ?? '#FFFFFF',
            'margin' => (int) ($settings['watermark_margin'] ?? 20),
        ];

        if ($watermarkEnabled && $watermarkSettings['text'] === '') {
            throw new InvalidArgumentException(
                'Watermark text is required when watermarking is enabled.'
            );
        }

        // Keep track of every GD image so each can be released once.
        $imagesToRelease = [];

        $sourceImage = null;

        try {
            // 1. Load the original image.
            $sourceImage = $this->processor->load($sourcePath);

            $imagesToRelease[spl_object_id($sourceImage)] = $sourceImage;

            $workingImage = $sourceImage;

            // 2. Resize only when dimensions have been provided.
            if ($width !== null || $height !== null) {
                $workingImage = $this->resizer->resize(
                    source: $workingImage,
                    width: $width,
                    height: $height,
                    mode: $resizeMode,
                    preventUpscaling: $settings['prevent_upscaling'] ?? true,
                );

                $imagesToRelease[spl_object_id($workingImage)] =
                    $workingImage;
            }

            // 4. Apply the optional text watermark.
            if ($watermarkEnabled) {
                $this->watermarker->apply(
                    $workingImage,
                    $watermarkSettings,
                );
            }

            // 3. JPEG does not support transparency.
            // Flatten transparent areas onto the chosen background.
            if ($outputFormat === 'jpg') {
                $flattenedImage = $this->flattenTransparency(
                    $workingImage,
                    $backgroundColor
                );

                $imagesToRelease[spl_object_id($flattenedImage)] =
                    $flattenedImage;

                $workingImage = $flattenedImage;
            }

            // 4. Encode the processed image.
            $encoded = $this->encoder->encode(
                image: $workingImage,
                outputPath: $outputPath,
                format: $outputFormat,
                quality: $quality,
            );

            // 5. Calculate output information.
            $outputSize = $encoded['size_bytes'];

            $savedBytes = $original['size_bytes'] - $outputSize;

            $savedPercentage = $original['size_bytes'] > 0
                ? round(
                    ($savedBytes / $original['size_bytes']) * 100,
                    2
                )
                : 0;

            return [
                'success' => true,

                'original' => [
                    'filename' => $original['filename'],
                    'format' => $original['format'],
                    'width' => $original['width'],
                    'height' => $original['height'],
                    'size_bytes' => $original['size_bytes'],
                    'size_kb' => $original['size_kb'],
                    'size_mb' => $original['size_mb'],
                ],

                'output' => [
                    'path' => $encoded['path'],
                    'filename' => basename($encoded['path']),
                    'format' => $encoded['format'],
                    'width' => imagesx($workingImage),
                    'height' => imagesy($workingImage),
                    'size_bytes' => $outputSize,
                    'size_kb' => $encoded['size_kb'],
                    'size_mb' => $encoded['size_mb'],
                ],

                'optimization' => [
                    'saved_bytes' => $savedBytes,
                    'saved_percentage' => $savedPercentage,
                    'quality' => $quality,
                    'resize_mode' => (
                        $width !== null || $height !== null
                    ) ? $resizeMode : null,

                    'watermark' => [
                        'enabled' => $watermarkEnabled,
                        'text' => $watermarkEnabled
                            ? $watermarkSettings['text']
                            : null,
                        'position' => $watermarkEnabled
                            ? $watermarkSettings['position']
                            : null,
                        'opacity' => $watermarkEnabled
                            ? $watermarkSettings['opacity']
                            : null,
                        'size' => $watermarkEnabled
                            ? $watermarkSettings['size']
                            : null,
                        'color' => $watermarkEnabled
                            ? $watermarkSettings['color']
                            : null,
                        'margin' => $watermarkEnabled
                            ? $watermarkSettings['margin']
                            : null,
                    ],
                ],
            ];
        } finally {
            // 6. Release every GD image, even if an exception occurs.
            foreach ($imagesToRelease as $image) {
                $this->processor->release($image);
            }
        }
    }

    /**
     * Flatten transparency onto a solid background.
     */
    protected function flattenTransparency(
        GdImage $source,
        string $backgroundColor
    ): GdImage {
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $backgroundColor)) {
            throw new InvalidArgumentException(
                'Background color must be a hexadecimal color, e.g. #FFFFFF.'
            );
        }

        $width = imagesx($source);
        $height = imagesy($source);

        $destination = imagecreatetruecolor($width, $height);

        if (!$destination instanceof GdImage) {
            throw new RuntimeException(
                'Unable to allocate the output image.'
            );
        }

        $red = hexdec(substr($backgroundColor, 1, 2));
        $green = hexdec(substr($backgroundColor, 3, 2));
        $blue = hexdec(substr($backgroundColor, 5, 2));

        $background = imagecolorallocate(
            $destination,
            $red,
            $green,
            $blue
        );

        if ($background === false) {
            imagedestroy($destination);

            throw new RuntimeException(
                'Unable to allocate the background color.'
            );
        }

        imagefill($destination, 0, 0, $background);

        if (!imagecopy(
            $destination,
            $source,
            0,
            0,
            0,
            0,
            $width,
            $height
        )) {
            imagedestroy($destination);

            throw new RuntimeException(
                'Unable to flatten image transparency.'
            );
        }

        return $destination;
    }
}