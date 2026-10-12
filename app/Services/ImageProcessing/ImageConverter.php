<?php

namespace App\Services\ImageProcessing;

use RuntimeException;

class ImageConverter
{
    public function __construct(
        private readonly ImageProcessor $processor,
        private readonly ImageEncoder $encoder
    ) {
    }

    /**
     * Convert an image to another format.
     */
    // public function convert(
    //     string $sourcePath,
    //     string $outputPath,
    //     string $outputFormat,
    //     int $quality = 80,
    //     string $backgroundColor = '#FFFFFF'
    // ): array {
    //     $image = $this->processor->load($sourcePath);

    //     try {
    //         $format = strtolower($outputFormat);

    //         if (in_array($format, ['jpg', 'jpeg'], true)) {
    //             $image = $this->flattenTransparency(
    //                 $image,
    //                 $backgroundColor
    //             );

    //             $format = 'jpg';
    //         }

    //         return $this->encoder->encode(
    //             $image,
    //             $outputPath,
    //             $format,
    //             $quality
    //         );
    //     } finally {
    //         $this->processor->release($image);
    //     }
    // }
    public function convert(
        string $sourcePath,
        string $outputPath,
        string $outputFormat,
        int $quality = 80,
        string $backgroundColor = '#FFFFFF'
    ): array {
        $source = $this->processor->load($sourcePath);
        $output = $source;

        try {
            $format = strtolower($outputFormat);

            if (in_array($format, ['jpg', 'jpeg'], true)) {
                $output = $this->flattenTransparency(
                    $source,
                    $backgroundColor
                );

                $format = 'jpg';
            }

            return $this->encoder->encode(
                $output,
                $outputPath,
                $format,
                $quality
            );
        } finally {
            if ($output !== $source) {
                $this->processor->release($output);
            }

            $this->processor->release($source);
        }
    }

    /**
     * Flatten transparency onto a solid background.
     */
    private function flattenTransparency(
        \GdImage $source,
        string $backgroundColor
    ): \GdImage {
        if (
            !preg_match(
                '/^#[0-9a-fA-F]{6}$/',
                $backgroundColor
            )
        ) {
            throw new RuntimeException(
                'Invalid background color. Use a hexadecimal color such as #FFFFFF.'
            );
        }

        $width = imagesx($source);
        $height = imagesy($source);

        $canvas = imagecreatetruecolor($width, $height);

        if (!$canvas instanceof \GdImage) {
            throw new RuntimeException(
                'Unable to create the output image canvas.'
            );
        }

        $red = hexdec(substr($backgroundColor, 1, 2));
        $green = hexdec(substr($backgroundColor, 3, 2));
        $blue = hexdec(substr($backgroundColor, 5, 2));

        $background = imagecolorallocate(
            $canvas,
            $red,
            $green,
            $blue
        );

        imagefill($canvas, 0, 0, $background);

        imagecopy(
            $canvas,
            $source,
            0,
            0,
            0,
            0,
            $width,
            $height
        );

        return $canvas;
    }
}