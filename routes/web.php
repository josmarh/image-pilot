<?php

use App\Http\Controllers\ImagePilotController;
use Illuminate\Support\Facades\Route;

Route::get('/', [ImagePilotController::class, 'optimizer'])
    ->name('imagepilot.optimizer');

Route::view('/batches', 'imagepilot.batches')
    ->name('imagepilot.batches');

Route::view('/account', 'imagepilot.account')
    ->name('imagepilot.account');