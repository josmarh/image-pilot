<?php

namespace App\Services\ImageProcessing;

use Illuminate\Http\UploadedFile;
use RuntimeException;

class ImageInspector
{
    /**
     * Supported image MIME types mapped to their formats.
     */
    private const SUPPORTED_FORMATS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Maximum allowed image width or height.
     */
    private const MAX_DIMENSION = 12000;

    /**
     * Maximum allowed number of pixels.
     */
    private const MAX_PIXELS = 40000000;

    /**
     * Inspect an uploaded image or an existing image file.
     *
     * @throws RuntimeException
     */
    public function inspect(UploadedFile|string $image): array
    {
        $path = $image instanceof UploadedFile
            ? $image->getRealPath()
            : $image;

        if (!$path || !is_file($path) || !is_readable($path)) {
            throw new RuntimeException(
                'The image file does not exist or cannot be read.'
            );
        }

        $fileSize = filesize($path);

        if ($fileSize === false || $fileSize <= 0) {
            throw new RuntimeException(
                'The image file is empty or its size cannot be determined.'
            );
        }

        // Detect the MIME type from the actual file contents.
        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        $mimeType = $finfo->file($path);

        if (!$mimeType || !isset(self::SUPPORTED_FORMATS[$mimeType])) {
            throw new RuntimeException(
                'Unsupported image format. Use JPG, PNG, or WebP.'
            );
        }

        // Read the image header to obtain dimensions and image type.
        $imageInfo = @getimagesize($path);

        if ($imageInfo === false) {
            throw new RuntimeException(
                'The uploaded file is not a valid image.'
            );
        }

        [$width, $height, $imageType] = $imageInfo;

        if ($width <= 0 || $height <= 0) {
            throw new RuntimeException(
                'The image has invalid dimensions.'
            );
        }

        if (
            $width > self::MAX_DIMENSION ||
            $height > self::MAX_DIMENSION
        ) {
            throw new RuntimeException(
                'The image exceeds the maximum allowed dimension of '
                . self::MAX_DIMENSION . ' pixels.'
            );
        }

        if (($width * $height) > self::MAX_PIXELS) {
            throw new RuntimeException(
                'The image exceeds the maximum allowed pixel count.'
            );
        }

        // Ensure the MIME type and detected image type agree.
        $expectedImageType = match ($mimeType) {
            'image/jpeg' => IMAGETYPE_JPEG,
            'image/png' => IMAGETYPE_PNG,
            'image/webp' => IMAGETYPE_WEBP,
        };

        if ($imageType !== $expectedImageType) {
            throw new RuntimeException(
                'The detected image format is inconsistent.'
            );
        }

        return [
            'path' => $path,
            'filename' => $image instanceof UploadedFile
                ? $image->getClientOriginalName()
                : basename($path),
            'mime_type' => $mimeType,
            'format' => self::SUPPORTED_FORMATS[$mimeType],
            'width' => $width,
            'height' => $height,
            'size_bytes' => $fileSize,
            'size_kb' => round($fileSize / 1024, 2),
            'size_mb' => round($fileSize / 1048576, 2),
            'has_transparency' => $mimeType !== 'image/jpeg',
        ];
    }
}