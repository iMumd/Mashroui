<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\InviteController;
use App\Http\Controllers\Api\SpecializationController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login']);
Route::post('/invite/{token}/accept', [InviteController::class, 'accept']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/me/change-password', [AuthController::class, 'changePassword']);

    Route::middleware('force-password-change')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/me/abilities', [AuthController::class, 'abilities']);
        Route::post('/users/{user}/invite', [InviteController::class, 'invite']);

        Route::apiResource('departments', DepartmentController::class);
        Route::apiResource('specializations', SpecializationController::class);
    });
});
