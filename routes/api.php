<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BotInteractionController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\TicketController;
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

    // Tickets
    Route::get('/tickets', [TicketController::class, 'index']);
    Route::post('/tickets', [TicketController::class, 'store']);
    Route::get('/tickets/{id}', [TicketController::class, 'show']);
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
});
