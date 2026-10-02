<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HistoryController;
use App\Http\Controllers\Api\JobController;
use App\Http\Controllers\Api\LlmKeyController;
use App\Http\Controllers\Api\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // BYOK provider keys
    Route::get('/llm/catalog', [LlmKeyController::class, 'catalog']);
    Route::apiResource('llm-keys', LlmKeyController::class);
    Route::post('/llm-keys/{llmKey}/test', [LlmKeyController::class, 'test']);
    Route::get('/llm-keys/{llmKey}/models', [LlmKeyController::class, 'models']);

    // Profil
    Route::get('/profiles', [ProfileController::class, 'index']);
    Route::post('/profiles', [ProfileController::class, 'store']);
    Route::get('/profiles/{profile}', [ProfileController::class, 'show']);
    Route::post('/profiles/{profile}/confirm', [ProfileController::class, 'confirm']);
    Route::delete('/profiles/{profile}', [ProfileController::class, 'destroy']);

    // Lowongan + pipeline (batch-generate sebelum /jobs/{job} agar tidak tertangkap param)
    Route::get('/jobs', [JobController::class, 'index']);
    Route::post('/jobs', [JobController::class, 'store']);
    Route::post('/jobs/batch-generate', [JobController::class, 'batchGenerate']);
    Route::get('/jobs/{job}', [JobController::class, 'show']);
    Route::post('/jobs/{job}/append', [JobController::class, 'append']);
    Route::post('/jobs/{job}/generate', [JobController::class, 'generate']);
    Route::post('/jobs/{job}/variant', [JobController::class, 'variant']);
    Route::post('/jobs/{job}/revise', [JobController::class, 'revise']);
    Route::delete('/jobs/{job}', [JobController::class, 'destroy']);

    // Riwayat pesan lamaran (generate / varian / copy)
    Route::get('/history', [HistoryController::class, 'index']);
    Route::post('/jobs/{job}/copied', [HistoryController::class, 'store']);
    Route::delete('/history/{id}', [HistoryController::class, 'destroy']);
});
