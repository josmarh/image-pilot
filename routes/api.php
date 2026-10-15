<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ImageProcessingBatchController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ImagePresetController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post(
    '/auth/login',
    [AuthController::class, 'login']
)->name('api.auth.login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post(
        '/image-processing/batches',
        [ImageProcessingBatchController::class, 'store']
    )->name('image-processing.batches.store');

    Route::get(
        '/image-processing/batches/{batchReference}',
        [ImageProcessingBatchController::class, 'show']
    )->name('image-processing.batches.show');

    Route::get(
        '/image-processing/batches/{batchReference}/items',
        [ImageProcessingBatchController::class, 'items']
    )->name('image-processing.batches.items');

    Route::get(
        '/image-processing/batches/{batchReference}/download',
        [ImageProcessingBatchController::class, 'download']
    )->name('image-processing.batches.download');


    Route::get('/image-presets', [
        ImagePresetController::class,
        'index',
    ])->name('image-presets.index');

    Route::post('/image-presets', [
        ImagePresetController::class,
        'store',
    ])->name('image-presets.store');

    Route::get('/image-presets/{id}', [
        ImagePresetController::class,
        'show',
    ])->whereNumber('id')->name('image-presets.show');

    Route::put('/image-presets/{id}', [
        ImagePresetController::class,
        'update',
    ])->whereNumber('id')->name('image-presets.update');

    Route::delete('/image-presets/{id}', [
        ImagePresetController::class,
        'destroy',
    ])->whereNumber('id')->name('image-presets.destroy');

    Route::get(
        '/image-processing/batches',
        [ImageProcessingBatchController::class, 'index']
    )->name('image-processing.batches.index');

    Route::get(
        '/image-processing/batches/{batchReference}/items/{item}/preview/{type}',
        [ImageProcessingBatchController::class, 'preview']
    )->whereIn('type', ['original', 'output'])
    ->name('image-processing.batches.items.preview');
});