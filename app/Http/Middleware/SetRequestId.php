<?php

namespace App\Http\Middleware;

use App\Http\Context\RequestContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Primer middleware de la cadena API.
 * Genera o preserva el X-Request-ID y crea el RequestContext inicial.
 */
class SetRequestId
{
    public function handle(Request $request, Closure $next): Response
    {
        $raw = $request->header('X-Request-ID');

        $requestId = ($raw && strlen($raw) >= 8 && strlen($raw) <= 64)
            ? $raw
            : 'req_'.Str::random(8);

        $context = new RequestContext(requestId: $requestId);
        app()->instance(RequestContext::class, $context);

        $response = $next($request);
        $response->headers->set('X-Request-ID', $requestId);

        return $response;
    }
}
