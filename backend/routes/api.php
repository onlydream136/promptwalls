<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\FileController;
use App\Http\Controllers\Api\ReIdentifyController;
use App\Http\Controllers\Api\SettingsController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WordPairController;
use App\Http\Middleware\AdminOnly;
use Illuminate\Support\Facades\Route;

// Auth (public)
Route::post('/auth/login', [AuthController::class, 'login']);

// File preview (supports token via query string for browser tab opening)
Route::get('/files/{id}/preview', [FileController::class, 'preview']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    // Auth
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

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
        Route::post('/{id}/desensitize', [FileController::class, 'desensitize']);
        Route::delete('/{id}', [FileController::class, 'destroy']);
    });

    // Re-identification
    Route::prefix('reidentify')->group(function () {
        Route::get('/files', [ReIdentifyController::class, 'listFiles']);
        Route::post('/upload', [ReIdentifyController::class, 'upload']);
        Route::post('/process', [ReIdentifyController::class, 'process']);
        Route::post('/restore', [ReIdentifyController::class, 'restore']);
        Route::get('/pairs/{fileId}', [ReIdentifyController::class, 'pairs']);
    });

    // Word Pairs Management
    Route::prefix('wordpairs')->group(function () {
        Route::get('/', [WordPairController::class, 'index']);
        Route::post('/', [WordPairController::class, 'store']);
        Route::put('/{id}', [WordPairController::class, 'update']);
        Route::delete('/{id}', [WordPairController::class, 'destroy']);
    });

    // Settings (admin only)
    Route::prefix('settings')->middleware(AdminOnly::class)->group(function () {
        Route::get('/', [SettingsController::class, 'index']);
        Route::put('/', [SettingsController::class, 'update']);
        Route::post('/test-connection', [SettingsController::class, 'testConnection']);
    });

    // User Management (admin only)
    Route::prefix('users')->middleware(AdminOnly::class)->group(function () {
        Route::get('/', [UserController::class, 'index']);
        Route::post('/', [UserController::class, 'store']);
        Route::put('/{id}', [UserController::class, 'update']);
        Route::delete('/{id}', [UserController::class, 'destroy']);
    });
});
