<?php

namespace App\Http\Middleware;

use App\Domain\Auth\Models\TenantApiClient;
use App\Domain\Tenants\TenantContext;
use App\Http\Context\RequestContext;
use App\Http\Responses\ApiResponse;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Se ejecuta después de auth:sanctum en el grupo de rutas protegidas.
 * Extrae tenant_id del cliente autenticado, activa TenantContext + RequestContext.
 * Rechaza clientes API inactivos (403) y usuarios sin tenant asociado (403).
 */
class SetTenantContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $client = $request->user();

        if ($client instanceof TenantApiClient || $client instanceof User) {

            // Rechazar clientes API desactivados
            if ($client instanceof TenantApiClient && ! $client->is_active) {
                return ApiResponse::forbidden('API client is inactive.');
            }

            $abilities = $client->currentAccessToken()?->abilities ?? [];
            $context   = app(RequestContext::class)->withAuth($client, $abilities);
            app()->instance(RequestContext::class, $context);

            // Sin tenant_id no se puede operar con seguridad en entorno multi-tenant
            if (! $context->tenantId) {
                return ApiResponse::forbidden('No tenant associated with this token.');
            }

            TenantContext::set($context->tenantId);
        }

        try {
            return $next($request);
        } finally {
            TenantContext::clear();
        }
    }
}
