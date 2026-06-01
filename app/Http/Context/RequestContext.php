<?php

namespace App\Http\Context;

use App\Domain\Auth\Models\TenantApiClient;
use App\Models\User;

/**
 * Contexto inmutable de la petición API en curso.
 * Se construye progresivamente por la cadena de middleware y se
 * registra en el contenedor para que los controllers puedan inyectarlo.
 */
class RequestContext
{
    public readonly string $requestId;
    public readonly ?string $tenantId;
    public readonly ?string $sourceSystem;
    public readonly ?string $sourceChannel;
    public readonly TenantApiClient|User|null $client;
    public readonly array $abilities;

    public function __construct(
        string $requestId,
        ?string $tenantId = null,
        ?string $sourceSystem = null,
        ?string $sourceChannel = null,
        TenantApiClient|User|null $client = null,
        array $abilities = [],
    ) {
        $this->requestId    = $requestId;
        $this->tenantId     = $tenantId;
        $this->sourceSystem = $sourceSystem;
        $this->sourceChannel = $sourceChannel;
        $this->client       = $client;
        $this->abilities    = $abilities;
    }

    public function withAuth(
        TenantApiClient|User $client,
        array $abilities,
    ): static {
        return new static(
            requestId:     $this->requestId,
            tenantId:      $client->tenant_id,
            sourceSystem:  $client instanceof TenantApiClient ? $client->source_system : null,
            sourceChannel: $client instanceof TenantApiClient ? $client->source_channel : null,
            client:        $client,
            abilities:     $abilities,
        );
    }

    public function hasAbility(string $ability): bool
    {
        return in_array('*', $this->abilities, true)
            || in_array($ability, $this->abilities, true);
    }
}
