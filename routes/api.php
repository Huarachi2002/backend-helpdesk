<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BotInteractionController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TicketController;
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

Route::middleware('auth:sanctum')->group(function () {
    
    // Auth
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // Categorías de Tickets
    Route::get('/categories', [CategoryController::class, 'index']);

    // Tickets
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{id}', [TicketController::class, 'show']);
    Route::get('/tickets/{id}/eligible-agents', [TicketController::class, 'eligibleAgents']);
    Route::patch('/tickets/{id}/status', [TicketController::class, 'updateStatus']);
    Route::patch('/tickets/{id}/assign', [TicketController::class, 'reassign']);

    // Auditoria de Interacciones (Bot)
    Route::get('/bot-interactions', [BotInteractionController::class, 'index']);
    Route::post('/bot-interactions', [BotInteractionController::class, 'store']);

    // Reportes
    Route::get('/reports/volume', [ReportController::class, 'volume']);
    Route::get('/reports/impact', [ReportController::class, 'impact']);
    Route::get('/reports/times', [ReportController::class, 'times']);
    Route::get('/reports/export', [ReportController::class, 'export']);

    // Gestión de Usuarios y Roles de Agente (Exclusivo Administrador)
    Route::middleware('admin')->group(function () {
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
    });
});
