<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\LeadActivityController;
use App\Http\Controllers\Api\V1\LeadController;
use App\Http\Controllers\Api\V1\LeadNoteController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes — leads-service
|--------------------------------------------------------------------------
| Nota: los parámetros {lead} son strings (UUID) resueltos explícitamente
| en cada controller para que TenantScope se aplique correctamente
| (SubstituteBindings ejecuta antes que set.tenant.context).
|
*/

// Health check — sin autenticación
Route::get('/health', fn () => response()->json([
    'status'    => 'ok',
    'service'   => 'leads-service',
    'version'   => '1.0.0',
    'timestamp' => now()->toISOString(),
]));

// Auth
Route::prefix('v1')->group(function () {

    Route::prefix('auth')->group(function () {
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:5,1');

        Route::post('logout', [AuthController::class, 'logout'])
            ->middleware(['auth:sanctum']);
    });

    // Rutas protegidas — auth:sanctum + tenant context
    Route::middleware(['auth:sanctum', 'set.tenant.context'])->group(function () {

        // ── Leads: listado y creación ────────────────────────────────────────
        Route::get('leads', [LeadController::class, 'index']);
        Route::post('leads', [LeadController::class, 'store']);

        // ── Leads: detalle ───────────────────────────────────────────────────
        Route::get('leads/{lead}', [LeadController::class, 'show']);

        // ── Leads: acciones de estado y pipeline ─────────────────────────────
        Route::patch('leads/{lead}/stage',    [LeadController::class, 'updateStage']);
        Route::patch('leads/{lead}/assign',   [LeadController::class, 'assign']);
        Route::patch('leads/{lead}/followup', [LeadController::class, 'followup']);
        Route::post('leads/{lead}/contact',   [LeadController::class, 'contact']);
        Route::patch('leads/{lead}/won',      [LeadController::class, 'won']);
        Route::patch('leads/{lead}/lost',     [LeadController::class, 'lost']);

        // ── Notas del lead ───────────────────────────────────────────────────
        Route::get('leads/{lead}/notes',  [LeadNoteController::class, 'index']);
        Route::post('leads/{lead}/notes', [LeadNoteController::class, 'store']);

        // ── Actividad del lead ───────────────────────────────────────────────
        Route::get('leads/{lead}/activity', [LeadActivityController::class, 'index']);
    });
});
