<?php

use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\ReIdentifyController;
use App\Http\Controllers\Api\SettingsController;
use Illuminate\Support\Facades\Route;

// Dashboard
Route::prefix('dashboard')->group(function () {
    Route::get('/stats', [DashboardController::class, 'stats']);
    Route::get('/recent', [DashboardController::class, 'recent']);
    Route::get('/throughput', [DashboardController::class, 'throughput']);
    Route::get('/monitor', [DashboardController::class, 'monitor']);
});

// File Management
Route::prefix('files')->group(function () {
    Route::get('/counts', [FileController::class, 'counts']);
    Route::get('/', [FileController::class, 'index']);
    Route::post('/upload', [FileController::class, 'upload']);
    Route::get('/{id}', [FileController::class, 'show']);
    Route::get('/{id}/download', [FileController::class, 'download']);
    Route::post('/{id}/retry', [FileController::class, 'retry']);
    Route::delete('/{id}', [FileController::class, 'destroy']);
});

// Re-identification
Route::prefix('reidentify')->group(function () {
    Route::get('/files', [ReIdentifyController::class, 'listFiles']);
    Route::post('/upload', [ReIdentifyController::class, 'upload']);
    Route::post('/process', [ReIdentifyController::class, 'process']);
    Route::get('/pairs/{fileId}', [ReIdentifyController::class, 'pairs']);
});

// Settings
Route::prefix('settings')->group(function () {
    Route::get('/', [SettingsController::class, 'index']);
    Route::put('/', [SettingsController::class, 'update']);
    Route::post('/test-connection', [SettingsController::class, 'testConnection']);
});
