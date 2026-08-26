<?php

use App\Http\Controllers\Api\AcademicTermController;
use App\Http\Controllers\Api\AiProjectSourceController;
use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BulkNotifyController;
use App\Http\Controllers\Api\CommitteeDashboardController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DiscussionController;
use App\Http\Controllers\Api\DiscussionExportController;
use App\Http\Controllers\Api\DiscussionImportController;
use App\Http\Controllers\Api\EmailRelayController;
use App\Http\Controllers\Api\FinalReportController;
use App\Http\Controllers\Api\InviteController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\MessageDeliveryController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\ProgressExportController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ProjectFileController;
use App\Http\Controllers\Api\ProposalController;
use App\Http\Controllers\Api\SpecializationController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskFileController;
use App\Http\Controllers\Api\TaskNoteController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TeamExportController;
use App\Http\Controllers\Api\TeamImportController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserRestrictionController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/invite/{token}/accept', [InviteController::class, 'accept'])->middleware('throttle:invite-accept');
Route::get('/projects/featured', [ProjectController::class, 'featured'])->middleware('throttle:public');
Route::get('/projects/public-archive', [ProjectController::class, 'publicArchive'])->middleware('throttle:public');
Route::get('/projects/public-archive/{project}', [ProjectController::class, 'publicArchiveShow'])->middleware('throttle:public');
Route::get('/departments/public', [DepartmentController::class, 'publicIndex'])->middleware('throttle:public');
Route::get('/stats', [StatsController::class, 'index'])->middleware('throttle:public');

Route::get('/committee/dashboard-stats', [CommitteeDashboardController::class, 'index'])
    ->middleware(['auth:sanctum', 'throttle:api', 'term']);

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/me/change-password', [AuthController::class, 'changePassword']);

    Route::middleware('force-password-change')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/me/abilities', [AuthController::class, 'abilities']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/trashed', [UserController::class, 'trashed']);
        Route::patch('/users/{user}', [UserController::class, 'update']);
        Route::patch('/users/{user}/status', [UserController::class, 'updateStatus']);
        Route::post('/users/{user}/invite', [InviteController::class, 'invite']);
        Route::post('/users/{user}/restore', [UserController::class, 'restore']);
        Route::post('/users/{user}/set-password', [UserController::class, 'setPassword']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);

        Route::post('/email/send', [EmailRelayController::class, 'send']);

        Route::apiResource('departments', DepartmentController::class);
        Route::apiResource('specializations', SpecializationController::class);
        Route::apiResource('academic-terms', AcademicTermController::class);

        Route::get('/users/{user}/restrictions', [UserRestrictionController::class, 'index']);
        Route::post('/users/{user}/restrictions', [UserRestrictionController::class, 'store']);
        Route::delete('/restrictions/{restriction}', [UserRestrictionController::class, 'destroy']);

        Route::post('/notify/bulk/preview', [BulkNotifyController::class, 'preview']);
        Route::post('/notify/bulk/send', [BulkNotifyController::class, 'send']);

        Route::get('/message-deliveries', [MessageDeliveryController::class, 'index']);
        Route::post('/message-deliveries/{delivery}/retry', [MessageDeliveryController::class, 'retry']);

        Route::get('/notifications', [NotificationController::class, 'index']);
        Route::post('/notifications/{notification}/read', [NotificationController::class, 'read']);
        Route::post('/notifications/read-all', [NotificationController::class, 'readAll']);

        Route::get('/ai/projects-source', [AiProjectSourceController::class, 'index']);

        Route::post('/assistant/chat', [AssistantController::class, 'chat'])->middleware('throttle:assistant');

        Route::middleware('term')->group(function () {
            Route::get('/users', [UserController::class, 'index']);

            Route::get('/teams/export', [TeamExportController::class, 'export']);
            Route::get('/teams/trashed', [TeamController::class, 'trashed']);
            Route::post('/teams/{team}/restore', [TeamController::class, 'restore']);

            Route::apiResource('teams', TeamController::class)->only(['index', 'store', 'show', 'update', 'destroy']);

            Route::post('/teams/{team}/members', [TeamController::class, 'addMember']);
            Route::delete('/teams/{team}/members/{member}', [TeamController::class, 'removeMember']);
            Route::patch('/teams/{team}/leader', [TeamController::class, 'updateLeader']);

            Route::post('/teams/import/preview', [TeamImportController::class, 'preview']);
            Route::post('/teams/import/confirm', [TeamImportController::class, 'confirm']);

            Route::post('/proposals', [ProposalController::class, 'store']);
            Route::get('/proposals/{proposal}', [ProposalController::class, 'show']);
            Route::get('/proposals/{proposal}/download', [ProposalController::class, 'download']);
            Route::put('/proposals/{proposal}', [ProposalController::class, 'update']);
            Route::post('/proposals/{proposal}/approve', [ProposalController::class, 'approve']);
            Route::post('/proposals/{proposal}/reject', [ProposalController::class, 'reject']);

            Route::get('/projects/archive', [ProjectController::class, 'archive']);
            Route::get('/projects/{project}', [ProjectController::class, 'show'])->whereNumber('project');
            Route::patch('/projects/{project}', [ProjectController::class, 'update']);
            Route::post('/projects/{project}/complete', [ProjectController::class, 'complete']);

            Route::get('/projects/{project}/files', [ProjectFileController::class, 'index']);
            Route::post('/projects/{project}/files', [ProjectFileController::class, 'store']);

            Route::get('/projects/{project}/final-reports', [FinalReportController::class, 'index']);
            Route::post('/projects/{project}/final-reports', [FinalReportController::class, 'store']);
            Route::get('/final-reports/{finalReport}/download', [FinalReportController::class, 'download']);

            Route::get('/teams/{team}/meetings', [MeetingController::class, 'index']);
            Route::post('/teams/{team}/meetings', [MeetingController::class, 'store']);
            Route::get('/meetings/{meeting}', [MeetingController::class, 'show']);
            Route::delete('/meetings/{meeting}', [MeetingController::class, 'destroy']);

            Route::get('/progress', [ProgressController::class, 'index']);
            Route::get('/progress/export', [ProgressExportController::class, 'export']);

            Route::get('/teams/{team}/progress', [TaskController::class, 'progress']);
            Route::get('/teams/{team}/tasks', [TaskController::class, 'index']);
            Route::post('/teams/{team}/tasks', [TaskController::class, 'store']);
            Route::get('/tasks/{task}', [TaskController::class, 'show']);
            Route::put('/tasks/{task}', [TaskController::class, 'update']);
            Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
            Route::patch('/tasks/{task}/status', [TaskController::class, 'changeStatus']);

            Route::get('/tasks/{task}/files', [TaskFileController::class, 'index']);
            Route::post('/tasks/{task}/files', [TaskFileController::class, 'store']);

            Route::get('/tasks/{task}/notes', [TaskNoteController::class, 'index']);
            Route::post('/tasks/{task}/notes', [TaskNoteController::class, 'store']);

            Route::get('/discussions/export', [DiscussionExportController::class, 'export']);
            Route::post('/discussions/import/preview', [DiscussionImportController::class, 'preview']);
            Route::post('/discussions/import/confirm', [DiscussionImportController::class, 'confirm']);
            Route::apiResource('discussions', DiscussionController::class)->except(['create', 'edit']);
        });
    });
});
