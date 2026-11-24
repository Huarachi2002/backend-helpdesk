<?php

use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BotInteractionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ConfigurationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PriorityController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketMessageController;
use App\Http\Controllers\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::post('/login', [AuthController::class, 'login']);

Route::middleware(['auth:sanctum', 'log.access'])->group(function () {
    
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Búsqueda Global
    Route::get('/search', [SearchController::class, 'search']);

    // Categorías de Tickets
    Route::get('/categories', [CategoryController::class, 'index']);

    // Prioridades y SLAs de Tickets
    Route::get('/priorities', [PriorityController::class, 'index']);

    // Tickets
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/reassignments/pending', [TicketController::class, 'pendingReassignments']);
    Route::get('/tickets/{id}', [TicketController::class, 'show']);
    Route::get('/tickets/{id}/eligible-agents', [TicketController::class, 'eligibleAgents']);
    Route::patch('/tickets/{id}/status', [TicketController::class, 'updateStatus']);
    Route::get('/tickets/{id}/messages', [TicketMessageController::class, 'index']);
    Route::post('/tickets/{id}/messages', [TicketMessageController::class, 'store']);
    Route::patch('/tickets/{id}/assign', [TicketController::class, 'reassign']);
    Route::post('/tickets/{id}/reassignments/{requestId}/approve', [TicketController::class, 'approveReassignment']);
    Route::post('/tickets/{id}/reassignments/{requestId}/reject', [TicketController::class, 'rejectReassignment']);

    // Auditoria de Interacciones (Bot)
    Route::get('/bot-interactions', [BotInteractionController::class, 'index']);
    Route::post('/bot-interactions', [BotInteractionController::class, 'store']);

    // Reportes
    Route::get('/reports/volume', [ReportController::class, 'volume']);
    Route::get('/reports/impact', [ReportController::class, 'impact']);
    Route::get('/reports/times', [ReportController::class, 'times']);
    Route::get('/reports/export', [ReportController::class, 'export']);
    Route::get('/reports/dashboard', [ReportController::class, 'dashboard']);

    // Notificaciones (Bandeja In-App del Usuario)
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::patch('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    // Gestión de Usuarios y Roles de Agente (Exclusivo Administrador)
    Route::middleware('admin')->group(function () {
        // Configuraciones del Sistema y Notificaciones (CRUD Admin)
        Route::get('/configurations', [ConfigurationController::class, 'index']);
        Route::put('/configurations', [ConfigurationController::class, 'update']);
        Route::post('/configurations/trigger-reminders', [ConfigurationController::class, 'triggerReminders']);

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::patch('/users/{id}/toggle-active', [UserController::class, 'toggleActive']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);

        // Gestión y Clasificación de Categorías (CRUD Admin)
        Route::post('/categories', [CategoryController::class, 'store']);
        Route::get('/categories/{id}', [CategoryController::class, 'show']);
        Route::put('/categories/{id}', [CategoryController::class, 'update']);
        Route::patch('/categories/{id}/toggle-active', [CategoryController::class, 'toggleActive']);
        Route::delete('/categories/{id}', [CategoryController::class, 'destroy']);

        // Gestión de Prioridades y SLAs (CRUD Admin)
        Route::post('/priorities', [PriorityController::class, 'store']);
        Route::get('/priorities/{id}', [PriorityController::class, 'show']);
        Route::put('/priorities/{id}', [PriorityController::class, 'update']);
        Route::patch('/priorities/{id}/toggle-active', [PriorityController::class, 'toggleActive']);
        Route::delete('/priorities/{id}', [PriorityController::class, 'destroy']);

        // Bitácora de Auditoría
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
        Route::get('/audit-logs/stats', [AuditLogController::class, 'stats']);
    });
});
