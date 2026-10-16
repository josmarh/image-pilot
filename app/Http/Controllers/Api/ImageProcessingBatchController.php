<?php

namespace App\Http\Controllers\Api;

use App\Models\ImageProcessingBatch;
use App\Models\ImageProcessingItem;
use App\Http\Requests\StoreImageBatchRequest;
use App\Http\Resources\ImageProcessingBatchResource;
use App\Http\Resources\ImageProcessingItemResource;
use App\Services\ImageProcessing\ImageBatchProcessingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;
use ZipArchive;
use RuntimeException;

class ImageProcessingBatchController extends Controller
{
    public function __construct(
        protected ImageBatchProcessingService $batchService,
    ) {}

    public function index(
        Request $request
    ): AnonymousResourceCollection {
        $batches = ImageProcessingBatch::query()
            ->where('user_id', $request->user()->id)
            ->with('items')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(15);

        return ImageProcessingBatchResource::collection($batches);
    }

    public function store(
        StoreImageBatchRequest $request
    ): JsonResponse {
        $batch = $this->batchService->queue(
            files: $request->file('images'),
            userId: $request->user()->id,
            settings: $request->processingSettings(),
        );

        return response()->json([
            'message' => 'Images uploaded successfully and queued for processing.',

            'data' => [
                'batch_reference' => $batch->batch_reference,
                'status' => $batch->status,
                'original_count' => $batch->original_count,
                'successful_count' => $batch->successful_count,
                'failed_count' => $batch->failed_count,
                'original_size_bytes' => $batch->original_size_bytes,
                'optimized_size_bytes' => $batch->optimized_size_bytes,
            ],
        ], 202);
    }

    public function show(
        Request $request,
        string $batchReference
    ): ImageProcessingBatchResource {
        $batch = ImageProcessingBatch::query()
            ->where('batch_reference', $batchReference)
            ->where('user_id', $request->user()->id)
            ->with('items')
            ->firstOrFail();

        return new ImageProcessingBatchResource($batch);
    }

    public function items(
        Request $request,
        string $batchReference
    ): AnonymousResourceCollection {
        $batch = ImageProcessingBatch::query()
            ->where('batch_reference', $batchReference)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $items = $batch->items()
            ->with('batch')
            ->orderBy('id')
            ->get();

        return ImageProcessingItemResource::collection($items);
    }

    public function download(
        Request $request,
        string $batchReference
    ): BinaryFileResponse {
        $batch = ImageProcessingBatch::query()
            ->where('batch_reference', $batchReference)
            ->where('user_id', $request->user()->id)
            ->with('items')
            ->firstOrFail();

        if (!in_array($batch->status, ['completed', 'partial'], true)) {
            abort(409, 'The batch has not finished processing.');
        }

        $disk = Storage::disk('local');

        // Collect existing output files before creating the archive.
        $items = $batch->items
            ->where('status', 'completed')
            ->filter(fn ($item) => filled($item->output_path));

        $files = [];

        foreach ($items as $item) {
            if (!$disk->exists($item->output_path)) {
                continue;
            }

            $absolutePath = $disk->path($item->output_path);

            if (!is_file($absolutePath) || !is_readable($absolutePath)) {
                continue;
            }

            $filename = basename(
                $item->output_filename ?: $absolutePath
            );

            $files[] = [
                'path' => $absolutePath,
                'filename' => $filename,
            ];
        }

        if (empty($files)) {
            abort(404, 'No processed files are available for download.');
        }

        $zipDirectory = storage_path('app/imagepilot/tmp');

        if (
            !is_dir($zipDirectory) &&
            !mkdir($zipDirectory, 0755, true) &&
            !is_dir($zipDirectory)
        ) {
            abort(500, 'Unable to prepare the download directory.');
        }

        // Unique archive paths prevent concurrent requests from overwriting
        // each other's ZIP files.
        $zipPath = $zipDirectory
            . '/'
            . $batch->batch_reference
            . '-'
            . Str::uuid()
            . '.zip';

        $zip = new ZipArchive();

        $opened = false;

        try {
            $openResult = $zip->open(
                $zipPath,
                ZipArchive::CREATE | ZipArchive::EXCL
            );

            if ($openResult !== true) {
                throw new RuntimeException(
                    'Unable to create the download archive.'
                );
            }

            $opened = true;

            // Prevent duplicate filenames, including case-only differences
            // that can conflict when extracted on some operating systems.
            $usedNames = [];

            foreach ($files as $file) {
                $filename = $file['filename'];

                $extension = pathinfo(
                    $filename,
                    PATHINFO_EXTENSION
                );

                $basename = pathinfo(
                    $filename,
                    PATHINFO_FILENAME
                );

                if ($basename === '') {
                    $basename = 'image';
                }

                $candidate = $filename;
                $counter = 2;

                while (isset($usedNames[strtolower($candidate)])) {
                    $suffix = '-' . $counter++;

                    $candidate = $basename
                        . $suffix
                        . ($extension !== ''
                            ? '.' . $extension
                            : '');
                }

                $usedNames[strtolower($candidate)] = true;

                if (!$zip->addFile($file['path'], $candidate)) {
                    throw new RuntimeException(
                        "Unable to add {$candidate} to the archive."
                    );
                }
            }

            if (!$zip->close()) {
                $opened = false;

                throw new RuntimeException(
                    'Unable to finalize the download archive.'
                );
            }

            $opened = false;

            clearstatcache(true, $zipPath);

            if (
                !is_file($zipPath) ||
                filesize($zipPath) === false ||
                filesize($zipPath) === 0
            ) {
                throw new RuntimeException(
                    'The generated download archive is empty.'
                );
            }

            return response()
                ->download(
                    $zipPath,
                    "imagepilot-{$batch->batch_reference}.zip"
                )
                ->deleteFileAfterSend(true);
        } catch (Throwable $exception) {
            if ($opened) {
                $zip->close();
            }

            if (is_file($zipPath)) {
                @unlink($zipPath);
            }

            report($exception);

            abort(500, 'Unable to prepare the ZIP download. Please try again.');
        }
    }

    public function preview(
        Request $request,
        string $batchReference,
        int $item,
        string $type
    ): BinaryFileResponse {
        $batch = ImageProcessingBatch::query()
            ->where('batch_reference', $batchReference)
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $imageItem = $batch->items()
            ->whereKey($item)
            ->where('status', 'completed')
            ->firstOrFail();

        $relativePath = match ($type) {
            'original' => $imageItem->original_path,
            'output' => $imageItem->output_path,
            default => abort(404),
        };

        abort_unless($relativePath, 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists($relativePath), 404);

        $mimeType = $disk->mimeType($relativePath);

        abort_unless(
            in_array($mimeType, [
                'image/jpeg',
                'image/png',
                'image/webp',
            ], true),
            404
        );

        return response()->file(
            $disk->path($relativePath),
            [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'private, no-store',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }
}
