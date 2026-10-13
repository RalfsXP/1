<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\EventController;
use App\Http\Controllers\Api\GoalController;
use App\Http\Controllers\Api\GoalStyleController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\SystemHealthController;
use App\Http\Controllers\Api\WhiteboardController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::middleware('throttle:10,1')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::patch('/user/locale', [AuthController::class, 'updateLocale']);
    Route::put('/user/goal-styles', [GoalStyleController::class, 'update']);
    Route::get('/admin/health', SystemHealthController::class);
    Route::post('/admin/migrate', [SystemHealthController::class, 'migrate'])->middleware('throttle:3,1');
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::apiResource('goals', GoalController::class)->except('show');
    Route::apiResource('whiteboards', WhiteboardController::class);
    Route::apiResource('events', EventController::class)->except('show');
    Route::put('/goals/{goal}/completions/{date}', [GoalController::class, 'complete']);
    Route::delete('/goals/{goal}/completions/{date}', [GoalController::class, 'uncomplete']);
});
