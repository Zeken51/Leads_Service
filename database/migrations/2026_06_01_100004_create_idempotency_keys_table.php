<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');

            // Header Idempotency-Key enviado por el cliente (nivel 1)
            $table->string('idempotency_key')->nullable();

            // Hash del request: method + path + body hash (nivel hash)
            $table->string('request_hash', 64);

            $table->string('method', 10);
            $table->string('path');

            // Contexto del lead para búsqueda rápida
            $table->string('source_system')->nullable();
            $table->string('source_channel')->nullable();
            $table->string('external_reference_id')->nullable();

            // Lead resultante (se rellena tras creación exitosa)
            $table->uuid('lead_id')->nullable();

            // Respuesta almacenada para replay
            $table->unsignedSmallInteger('response_status');
            $table->json('response_body');

            $table->timestamp('expires_at');
            $table->timestamp('created_at')->useCurrent();

            // Búsqueda rápida por clave de header
            $table->index(['idempotency_key', 'tenant_id']);
            // Búsqueda por hash de request
            $table->index('request_hash');
            // Limpieza de expirados
            $table->index('expires_at');

            // Unicidad: un tenant no puede tener dos registros con la misma key de header
            $table->unique(['tenant_id', 'idempotency_key'], 'idempotency_tenant_key_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
