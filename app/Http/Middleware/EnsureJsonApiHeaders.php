<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Garantiza que las peticiones a /api/* usen JSON correctamente.
 * Rechaza peticiones con Content-Type incorrecto en métodos con body.
 */
class EnsureJsonApiHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        // Forzar que la request sea tratada como JSON (activa $request->expectsJson())
        $request->headers->set('Accept', 'application/json');

        // Validar Content-Type en peticiones con body
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'])) {
            $contentType = $request->header('Content-Type', '');
            $hasBody     = $request->getContent() !== '';

            if ($hasBody && ! str_contains($contentType, 'application/json')) {
                return ApiResponse::error(
                    message: 'Content-Type must be application/json.',
                    status: Response::HTTP_UNSUPPORTED_MEDIA_TYPE,
                );
            }
        }

        return $next($request);
    }
}
