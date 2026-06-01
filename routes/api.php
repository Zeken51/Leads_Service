<?php

use App\Http\Controllers\Api\V1\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1 Routes — leads-service
|--------------------------------------------------------------------------
| Middleware aplicado en bootstrap/app.php:
|   api-v1 group: EnsureJsonApiHeaders, SetRequestId
|   auth group:   auth:sanctum, SetTenantContext
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

    // Rutas protegidas (leads, pipeline, notas, etc. — se agregan en fases 6.6/6.7)
    Route::middleware(['auth:sanctum', 'set.tenant.context'])->group(function () {
        // Fase 6.6: POST /leads, GET /leads, etc.
    });

});
