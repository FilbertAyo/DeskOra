<?php

use App\Http\Controllers\Api\PeriodStatsController;
use App\Http\Controllers\DocumentDashboardController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->prefix('period-stats')->group(function () {
    Route::get('/current', [PeriodStatsController::class, 'current']);
    Route::get('/timeline', [PeriodStatsController::class, 'timeline']);
    Route::get('/overview', [PeriodStatsController::class, 'overview']);
});

Route::middleware('auth:sanctum')->prefix('documents')->group(function () {
    Route::get('/plan-target', [DocumentDashboardController::class, 'getPlanTarget']);
});
