<?php

namespace App\Http\Controllers\Api;

use App\Models\ImageProcessingBatch;
use App\Http\Requests\StoreImageBatchRequest;
use App\Http\Resources\ImageProcessingBatchResource;
use App\Http\Resources\ImageProcessingItemResource;
use App\Services\ImageProcessing\ImageBatchProcessingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

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

        if (!in_array($batch->status, [
            'completed',
            'partial',
        ], true)) {
            abort(409, 'The batch has not finished processing.');
        }

        $items = $batch->items
            ->where('status', 'completed')
            ->filter(
                fn ($item) => filled($item->output_path)
            );

        if ($items->isEmpty()) {
            abort(404, 'No processed files are available for download.');
        }

        $disk = Storage::disk('local');

        $zipDirectory = storage_path('app/imagepilot/tmp');

        if (!is_dir($zipDirectory)) {
            mkdir($zipDirectory, 0755, true);
        }

        $zipPath = $zipDirectory . '/' . $batch->batch_reference . '.zip';

        $zip = new ZipArchive();

        if ($zip->open(
            $zipPath,
            ZipArchive::CREATE | ZipArchive::OVERWRITE
        ) !== true) {
            abort(500, 'Unable to create the download archive.');
        }

        foreach ($items as $item) {
            if (!$disk->exists($item->output_path)) {
                continue;
            }

            $absolutePath = $disk->path($item->output_path);

            $filename = $item->output_filename
                ?: basename($absolutePath);

            $zip->addFile(
                $absolutePath,
                $filename
            );
        }

        $zip->close();

        if (!file_exists($zipPath) || filesize($zipPath) === 0) {
            @unlink($zipPath);

            abort(
                404,
                'No processed files are available for download.'
            );
        }

        return response()
            ->download(
                $zipPath,
                "imagepilot-{$batch->batch_reference}.zip"
            )
            ->deleteFileAfterSend(true);
    }
}
