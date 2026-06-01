<?php

namespace App\Services;

use App\Domain\Idempotency\Models\IdempotencyKey;

class IdempotencyService
{
    /**
     * Genera un hash determinista del request para deduplicación por contenido.
     * SHA-256 de: METHOD|path|json_sorted_body
     */
    public function buildRequestHash(string $method, string $path, array $body): string
    {
        $this->sortRecursive($body);
        $payload = strtoupper($method).'|'.$path.'|'.json_encode($body, JSON_UNESCAPED_UNICODE);

        return hash('sha256', $payload);
    }

    /** Busca un registro activo (no expirado) por clave de header y tenant. */
    public function findActiveByKey(string $key, string $tenantId): ?IdempotencyKey
    {
        return IdempotencyKey::where('idempotency_key', $key)
            ->where('tenant_id', $tenantId)
            ->where('expires_at', '>', now())
            ->first();
    }

    /** Persiste el resultado de una operación idempotente. */
    public function store(
        ?string $key,
        string $tenantId,
        string $requestHash,
        string $method,
        string $path,
        ?string $sourceSystem,
        ?string $sourceChannel,
        ?string $externalReferenceId,
        ?string $leadId,
        int $responseStatus,
        array $responseBody,
    ): IdempotencyKey {
        $ttlHours = (int) config('leads.idempotency_ttl_hours', 24);

        return IdempotencyKey::create([
            'tenant_id'              => $tenantId,
            'idempotency_key'        => $key,
            'request_hash'           => $requestHash,
            'method'                 => $method,
            'path'                   => $path,
            'source_system'          => $sourceSystem,
            'source_channel'         => $sourceChannel,
            'external_reference_id'  => $externalReferenceId,
            'lead_id'                => $leadId,
            'response_status'        => $responseStatus,
            'response_body'          => $responseBody,
            'expires_at'             => now()->addHours($ttlHours),
        ]);
    }

    private function sortRecursive(array &$array): void
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $this->sortRecursive($value);
            }
        }
        ksort($array);
    }
}
