<?php

use App\Http\Middleware\EnsureJsonApiHeaders;
use App\Http\Middleware\SetRequestId;
use App\Http\Middleware\SetTenantContext;
use App\Http\Responses\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Middleware del panel web (Inertia)
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Middleware aplicado a todas las rutas API
        $middleware->api(prepend: [
            EnsureJsonApiHeaders::class,
            SetRequestId::class,
        ]);

        // Alias de middleware para uso en rutas
        $middleware->alias([
            'set.tenant.context' => SetTenantContext::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Todas las excepciones en rutas API se formatean con el envelope estándar
        $exceptions->renderable(function (\Throwable $e, Request $request): mixed {
            if (! $request->expectsJson()) {
                return null;
            }

            if ($e instanceof ValidationException) {
                return ApiResponse::validationError($e->errors());
            }

            if ($e instanceof AuthenticationException) {
                return ApiResponse::unauthorized();
            }

            if ($e instanceof NotFoundHttpException) {
                return ApiResponse::notFound();
            }

            if ($e instanceof HttpException) {
                return ApiResponse::error($e->getMessage() ?: 'HTTP error.', [], $e->getStatusCode());
            }

            // 500 — nunca exponer detalles en producción
            $message = config('app.debug')
                ? $e->getMessage()
                : 'An unexpected error occurred. Please contact support with the request_id.';

            return ApiResponse::error($message, [], 500);
        });
    })->create();
