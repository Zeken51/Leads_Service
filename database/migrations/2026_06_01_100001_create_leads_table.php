<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leads', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');

            // Origen
            $table->string('source_system');
            $table->string('source_channel');
            $table->string('external_reference_id')->nullable();

            // Pipeline
            $table->string('status', 20)->default('active');
            $table->uuid('stage_id')->nullable();
            $table->string('priority', 20)->default('medium');

            // Customer (embebido — no tabla separada)
            $table->string('customer_name');
            $table->string('customer_email')->nullable();
            $table->string('customer_phone', 30)->nullable();
            $table->string('customer_country', 2)->nullable();
            $table->json('customer_metadata')->nullable();

            // Asignación externa (snapshots — no FK a tabla de usuarios)
            $table->string('assigned_user_id')->nullable();
            $table->string('assigned_user_name_snapshot')->nullable();
            $table->string('assigned_user_email_snapshot')->nullable();
            $table->string('assigned_user_provider', 50)->nullable();

            // Seguimiento
            $table->string('next_action')->nullable();
            $table->timestamp('followup_at')->nullable();
            $table->timestamp('last_contact_at')->nullable();

            // Cierre
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->string('lost_reason')->nullable();

            // Metadata libre por source_system
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            // Índices de búsqueda frecuente
            $table->index('tenant_id');
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'stage_id']);
            $table->index(['tenant_id', 'assigned_user_id']);
            $table->index(['tenant_id', 'followup_at']);
            $table->index(['tenant_id', 'source_system']);

            // Unicidad semántica (idempotencia nivel 2)
            // Permite múltiples leads con external_reference_id NULL
            $table->unique(['tenant_id', 'source_system', 'external_reference_id'], 'leads_tenant_source_ext_ref_unique');

            $table->foreign('stage_id')
                ->references('id')
                ->on('pipeline_stages')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads');
    }
};
