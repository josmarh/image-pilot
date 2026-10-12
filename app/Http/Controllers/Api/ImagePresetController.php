<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreImagePresetRequest;
use App\Http\Resources\ImagePresetResource;
use App\Models\ImagePreset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ImagePresetController extends Controller
{
    public function index(
        Request $request
    ): AnonymousResourceCollection {
        $userId = $request->user()->id;

        $presets = ImagePreset::query()
            ->where(function ($query) use ($userId) {
                $query
                    ->where('is_system', true)
                    ->orWhere(function ($query) use ($userId) {
                        $query
                            ->where('user_id', $userId)
                            ->where('is_system', false);
                    });
            })
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        return ImagePresetResource::collection($presets);
    }

    public function store(
        StoreImagePresetRequest $request
    ): JsonResponse {
        $preset = ImagePreset::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'is_system' => false,
        ]);

        return (new ImagePresetResource($preset))
            ->response()
            ->setStatusCode(201);
    }

    public function show(
        Request $request,
        int $id
    ): ImagePresetResource {
        return new ImagePresetResource(
            $this->findAccessiblePreset($request, $id)
        );
    }

    public function update(
        StoreImagePresetRequest $request,
        int $id
    ): ImagePresetResource {
        $preset = $this->findCustomPreset($request, $id);

        $preset->update($request->validated());

        return new ImagePresetResource($preset->refresh());
    }

    public function destroy(
        Request $request,
        int $id
    ): JsonResponse {
        $preset = $this->findCustomPreset($request, $id);

        $preset->delete();

        return response()->json([
            'message' => 'Preset deleted successfully.',
        ]);
    }

    private function findAccessiblePreset(
        Request $request,
        int $id
    ): ImagePreset {
        return ImagePreset::query()
            ->whereKey($id)
            ->where(function ($query) use ($request) {
                $query
                    ->where('is_system', true)
                    ->orWhere(function ($query) use ($request) {
                        $query
                            ->where('user_id', $request->user()->id)
                            ->where('is_system', false);
                    });
            })
            ->firstOrFail();
    }

    private function findCustomPreset(
        Request $request,
        int $id
    ): ImagePreset {
        return ImagePreset::query()
            ->whereKey($id)
            ->where('user_id', $request->user()->id)
            ->where('is_system', false)
            ->firstOrFail();
    }
}