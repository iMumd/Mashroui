<?php

use App\Http\Controllers\Api\AcademicTermController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\FinalReportController;
use App\Http\Controllers\Api\InviteController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\ProjectFileController;
use App\Http\Controllers\Api\ProposalController;
use App\Http\Controllers\Api\SpecializationController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TeamExportController;
use App\Http\Controllers\Api\TeamImportController;
use App\Http\Controllers\Api\UserRestrictionController;
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
        Route::apiResource('academic-terms', AcademicTermController::class);

        Route::get('/users/{user}/restrictions', [UserRestrictionController::class, 'index']);
        Route::post('/users/{user}/restrictions', [UserRestrictionController::class, 'store']);
        Route::delete('/restrictions/{restriction}', [UserRestrictionController::class, 'destroy']);

        Route::middleware('term')->group(function () {
            Route::get('/teams/export', [TeamExportController::class, 'export']);

            Route::apiResource('teams', TeamController::class)->only(['index', 'store', 'show']);

            Route::post('/teams/import/preview', [TeamImportController::class, 'preview']);
            Route::post('/teams/import/confirm', [TeamImportController::class, 'confirm']);

            Route::post('/proposals', [ProposalController::class, 'store']);
            Route::get('/proposals/{proposal}', [ProposalController::class, 'show']);
            Route::put('/proposals/{proposal}', [ProposalController::class, 'update']);
            Route::post('/proposals/{proposal}/approve', [ProposalController::class, 'approve']);
            Route::post('/proposals/{proposal}/reject', [ProposalController::class, 'reject']);

            Route::get('/projects/{project}/files', [ProjectFileController::class, 'index']);
            Route::post('/projects/{project}/files', [ProjectFileController::class, 'store']);

            Route::get('/projects/{project}/final-reports', [FinalReportController::class, 'index']);
            Route::post('/projects/{project}/final-reports', [FinalReportController::class, 'store']);

            Route::get('/teams/{team}/meetings', [MeetingController::class, 'index']);
            Route::post('/teams/{team}/meetings', [MeetingController::class, 'store']);
            Route::get('/meetings/{meeting}', [MeetingController::class, 'show']);
        });
    });
});
