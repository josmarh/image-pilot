<?php

namespace App\Services\ImageProcessing;

use GdImage;
use RuntimeException;

class ImageProcessor
{
    public function __construct(
        private readonly ImageInspector $inspector
    ) {}

    /**
     * Load an image into a GD image resource.
     *
     * @throws RuntimeException
     */
    public function load(string $path): GdImage
    {
        $imageInfo = $this->inspector->inspect($path);

        $image = match ($imageInfo['format']) {
            'jpg' => @imagecreatefromjpeg($path),
            'png' => @imagecreatefrompng($path),
            'webp' => function_exists('imagecreatefromwebp')
                ? @imagecreatefromwebp($path)
                : false,
            default => false,
        };

        if (!$image instanceof GdImage) {
            throw new RuntimeException(
                'Unable to load the image. Check your PHP GD installation '
                . 'and supported image formats.'
            );
        }

        return $image;
    }

    /**
     * Release a GD image resource.
     */
    public function release(?GdImage $image): void
    {
        if ($image instanceof GdImage) {
            imagedestroy($image);
        }
    }
}