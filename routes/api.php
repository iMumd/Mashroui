<?php

use App\Http\Controllers\Api\AcademicTermController;
use App\Http\Controllers\Api\AnnouncementController;
use App\Http\Controllers\Api\AiProjectExportController;
use App\Http\Controllers\Api\AiProjectSourceController;
use App\Http\Controllers\Api\AssistantController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BulkNotifyController;
use App\Http\Controllers\Api\CommitteeDashboardController;
use App\Http\Controllers\Api\ComposeTemplateController;
use App\Http\Controllers\Api\DepartmentController;
use App\Http\Controllers\Api\DiscussionController;
use App\Http\Controllers\Api\DiscussionExportController;
use App\Http\Controllers\Api\DiscussionImportController;
use App\Http\Controllers\Api\EmailRelayController;
use App\Http\Controllers\Api\FinalReportController;
use App\Http\Controllers\Api\InviteController;
use App\Http\Controllers\Api\MagicLoginController;
use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\MessageDeliveryController;
use App\Http\Controllers\Api\MessageTemplateController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ProgressController;
use App\Http\Controllers\Api\ProgressExportController;
use App\Http\Controllers\Api\ProfileController;
use App\Http\Controllers\Api\ProjectController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ProjectFileController;
use App\Http\Controllers\Api\ProposalController;
use App\Http\Controllers\Api\SpecializationController;
use App\Http\Controllers\Api\StatsController;
use App\Http\Controllers\Api\EvaluationController;
use App\Http\Controllers\Api\EvaluationFormController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskFileController;
use App\Http\Controllers\Api\TaskNoteController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TeamExportController;
use App\Http\Controllers\Api\TeamImportController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\UserRestrictionController;
use App\Http\Controllers\Api\WhatsappSendController;
use Illuminate\Support\Facades\Route;

Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:login');
Route::post('/invite/{token}/accept', [InviteController::class, 'accept'])->middleware('throttle:invite-accept');
Route::post('/magic-login/{token}', [MagicLoginController::class, 'consume'])->middleware('throttle:invite-accept');
Route::get('/projects/featured', [ProjectController::class, 'featured'])->middleware('throttle:public');
Route::get('/projects/public-archive', [ProjectController::class, 'publicArchive'])->middleware('throttle:public');
Route::get('/projects/public-archive/{project}', [ProjectController::class, 'publicArchiveShow'])->middleware('throttle:public');
Route::get('/departments/public', [DepartmentController::class, 'publicIndex'])->middleware('throttle:public');
Route::get('/stats', [StatsController::class, 'index'])->middleware('throttle:public');

// نقطة وصول خارجية محمية بمفتاح ثابت — للدكتور/نموذج الـ AI الخاص فيه، عشان يقارن نسبة تشابه
// المقترحات الجديدة بالمشاريع الموجودة. مفتاح Bearer ثابت بدل جلسة مستخدم لأنه استهلاك خدمة-لخدمة.
Route::get('/external/ai/projects', [AiProjectExportController::class, 'index'])
    ->middleware(['ai-key', 'throttle:public']);

// يمرّر ال AI agent رقم الطالب (student_id) اللي بيحكي معه، فيرجعله فريقه وهل مشروع الفريق معتمد (تجاوز مرحلة proposed) ولا لسا.
Route::get('/external/ai/student-team', [AiProjectExportController::class, 'studentTeam'])
    ->middleware(['ai-key', 'throttle:public']);

Route::get('/committee/dashboard-stats', [CommitteeDashboardController::class, 'index'])
    ->middleware(['auth:sanctum', 'throttle:api', 'term']);

// روابط تحميل موقّعة مؤقتة — تُفتح مباشرة بالمتصفح بدون توكن (التوقيع نفسه هو التفويض)، عشان تتفادى
// مشاكل تحميل blob عبر JS (حاجب النوافذ المنبثقة، إضافات المتصفح، إلخ)
Route::get('/proposals/{proposal}/download-signed', [ProposalController::class, 'downloadSigned'])
    ->name('proposals.download-signed')->middleware('signed');
Route::get('/final-reports/{finalReport}/download-signed', [FinalReportController::class, 'downloadSigned'])
    ->name('final-reports.download-signed')->middleware('signed');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/me/change-password', [AuthController::class, 'changePassword']);

    Route::middleware('force-password-change')->group(function () {
        Route::get('/me', [AuthController::class, 'me']);
        Route::get('/me/abilities', [AuthController::class, 'abilities']);
        Route::patch('/me', [MeController::class, 'update']);
        Route::post('/me/avatar', [MeController::class, 'avatar']);
        Route::post('/users', [UserController::class, 'store'])->middleware('term');
        Route::get('/users/trashed', [UserController::class, 'trashed']);
        Route::patch('/users/{user}', [UserController::class, 'update']);
        Route::patch('/users/{user}/status', [UserController::class, 'updateStatus']);
        Route::post('/users/{user}/invite', [InviteController::class, 'invite']);
        Route::post('/users/{user}/restore', [UserController::class, 'restore']);
        Route::post('/users/{user}/set-password', [UserController::class, 'setPassword']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);

        Route::post('/email/send', [EmailRelayController::class, 'send']);
        Route::post('/whatsapp/send', [WhatsappSendController::class, 'send']);

        Route::get('/message-templates', [MessageTemplateController::class, 'index']);
        Route::post('/message-templates', [MessageTemplateController::class, 'store']);
        Route::put('/message-templates/{messageTemplate}', [MessageTemplateController::class, 'update']);
        Route::delete('/message-templates/{messageTemplate}', [MessageTemplateController::class, 'destroy']);

        Route::get('/compose-templates', [ComposeTemplateController::class, 'index']);
        Route::get('/compose-templates/files/{file}/download', [ComposeTemplateController::class, 'downloadFile']);
        Route::post('/compose-templates', [ComposeTemplateController::class, 'store']);
        Route::put('/compose-templates/{composeTemplate}', [ComposeTemplateController::class, 'update']);
        Route::delete('/compose-templates/{composeTemplate}', [ComposeTemplateController::class, 'destroy']);

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

        // نظام الدعم (التذاكر) — بديل دعم الواتساب
        Route::get('/tickets', [TicketController::class, 'index']);
        Route::post('/tickets', [TicketController::class, 'store']);
        Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
        Route::post('/tickets/{ticket}/replies', [TicketController::class, 'reply']);
        Route::post('/tickets/{ticket}/close', [TicketController::class, 'close']);
        Route::post('/tickets/{ticket}/reopen', [TicketController::class, 'reopen']);
        Route::post('/tickets/{ticket}/archive', [TicketController::class, 'archive']);
        Route::post('/tickets/{ticket}/unarchive', [TicketController::class, 'unarchive']);
        Route::delete('/tickets/{ticket}', [TicketController::class, 'destroy']);

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

            Route::get('/proposals', [ProposalController::class, 'index']);
            Route::post('/proposals', [ProposalController::class, 'store']);
            Route::get('/proposals/{proposal}', [ProposalController::class, 'show']);
            Route::get('/proposals/{proposal}/history', [ProposalController::class, 'history']);
            Route::get('/proposals/{proposal}/download', [ProposalController::class, 'download']);
            Route::get('/proposals/{proposal}/download-link', [ProposalController::class, 'downloadLink']);
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
            Route::get('/final-reports/{finalReport}/download-link', [FinalReportController::class, 'downloadLink']);
            Route::post('/final-reports/{finalReport}/review', [FinalReportController::class, 'review']);

            Route::get('/teams/{team}/meetings', [MeetingController::class, 'index']);
            Route::post('/teams/{team}/meetings', [MeetingController::class, 'store']);
            Route::get('/meetings/{meeting}', [MeetingController::class, 'show']);
            Route::delete('/meetings/{meeting}', [MeetingController::class, 'destroy']);

            Route::get('/supervisors/{user}/profile', [ProfileController::class, 'supervisor']);
            Route::get('/students/{user}/profile', [ProfileController::class, 'student']);
            Route::get('/reports/supervisors', [ReportController::class, 'supervisors']);

            Route::get('/progress', [ProgressController::class, 'index']);
            Route::get('/progress/export', [ProgressExportController::class, 'export']);

            Route::get('/teams/{team}/progress', [TaskController::class, 'progress']);
            Route::get('/teams/{team}/tasks/trashed', [TaskController::class, 'trashed']);
            Route::get('/teams/{team}/tasks', [TaskController::class, 'index']);
            Route::post('/teams/{team}/tasks', [TaskController::class, 'store']);
            Route::get('/tasks', [TaskController::class, 'overview']);
            Route::post('/staff/tasks', [TaskController::class, 'storeForStaff']);
            Route::post('/tasks/batch', [TaskController::class, 'storeForTeams']);
            Route::post('/tasks/{task}/restore', [TaskController::class, 'restore']);
            Route::delete('/tasks/{task}/force', [TaskController::class, 'forceDelete']);
            Route::get('/tasks/{task}', [TaskController::class, 'show']);
            Route::get('/tasks/{task}/activity', [TaskController::class, 'activity']);
            Route::put('/tasks/{task}', [TaskController::class, 'update']);
            Route::delete('/tasks/{task}', [TaskController::class, 'destroy']);
            Route::patch('/tasks/{task}/status', [TaskController::class, 'changeStatus']);

            Route::get('/tasks/{task}/files', [TaskFileController::class, 'index']);
            Route::post('/tasks/{task}/files', [TaskFileController::class, 'store']);

            Route::get('/tasks/{task}/notes', [TaskNoteController::class, 'index']);
            Route::post('/tasks/{task}/notes', [TaskNoteController::class, 'store']);

            Route::get('/evaluations', [EvaluationController::class, 'index']);
            Route::get('/evaluations/examining', [EvaluationFormController::class, 'examining']);
            Route::get('/evaluation-forms', [EvaluationFormController::class, 'index']);
            Route::post('/evaluation-forms', [EvaluationFormController::class, 'store']);
            Route::put('/evaluation-forms/{form}', [EvaluationFormController::class, 'update']);
            Route::delete('/evaluation-forms/{form}', [EvaluationFormController::class, 'destroy']);
            Route::get('/teams/{team}/evaluation', [EvaluationFormController::class, 'sheet']);
            Route::put('/teams/{team}/evaluation/{form}', [EvaluationFormController::class, 'submit']);

            Route::get('/announcements', [AnnouncementController::class, 'inbox']);
            Route::post('/announcements/send', [AnnouncementController::class, 'send']);
            Route::post('/announcements/ratings', [AnnouncementController::class, 'sendRatings']);
            Route::get('/announcements/sent', [AnnouncementController::class, 'sent']);
            Route::delete('/announcements/batch/{batch}', [AnnouncementController::class, 'destroyBatch']);
            Route::get('/announcements/attachments/{attachment}/download', [AnnouncementController::class, 'downloadAttachment']);
            Route::post('/announcements/supervisor', [AnnouncementController::class, 'storeForSupervisor']);
            Route::get('/teams/{team}/announcements', [AnnouncementController::class, 'indexForTeam']);
            Route::post('/teams/{team}/announcements', [AnnouncementController::class, 'storeForTeam']);
            Route::put('/teams/{team}/students/{student}/evaluation', [EvaluationController::class, 'upsert']);

            Route::get('/discussions/export', [DiscussionExportController::class, 'export']);
            Route::get('/discussions/import/template', [DiscussionImportController::class, 'template']);
            Route::post('/discussions/import/preview', [DiscussionImportController::class, 'preview']);
            Route::post('/discussions/import/confirm', [DiscussionImportController::class, 'confirm']);
            Route::apiResource('discussions', DiscussionController::class)->except(['create', 'edit']);
        });
    });
});
