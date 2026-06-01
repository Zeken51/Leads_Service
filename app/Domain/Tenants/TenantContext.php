<?php

namespace App\Domain\Tenants;

/**
 * Portador estático del tenant_id activo para el request en curso.
 *
 * Ciclo de vida:
 *   HTTP request → middleware SetTenantFromToken llama a set() y luego clear() en finally
 *   Queue job     → el job recibe tenant_id como propiedad y llama a withTenant()
 *   Seeders/tests → llamar a set() manualmente o usar withTenant()
 */
class TenantContext
{
    protected static ?string $tenantId = null;

    public static function set(string $tenantId): void
    {
        static::$tenantId = $tenantId;
    }

    public static function getId(): ?string
    {
        return static::$tenantId;
    }

    public static function clear(): void
    {
        static::$tenantId = null;
    }

    public static function isSet(): bool
    {
        return static::$tenantId !== null;
    }

    /**
     * Ejecuta un callback en el contexto de un tenant específico,
     * garantizando que el contexto se limpia al finalizar (incluso con excepciones).
     *
     * Uso en jobs: TenantContext::withTenant($this->tenantId, fn() => ...);
     * Uso en tests: TenantContext::withTenant($tenantId, fn() => ...);
     */
    public static function withTenant(string $tenantId, callable $callback): mixed
    {
        $previous = static::$tenantId;
        static::set($tenantId);
        try {
            return $callback();
        } finally {
            // Restaura el estado anterior (útil en tests con tenants anidados)
            $previous !== null ? static::set($previous) : static::clear();
        }
    }
}
