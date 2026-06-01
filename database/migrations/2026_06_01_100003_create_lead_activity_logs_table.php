<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_activity_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->uuid('tenant_id');

            // Evento (ver LeadEvent enum para valores válidos)
            $table->string('event_type', 50);
            $table->string('description');

            // Payload del evento: before/after, datos contextuales
            $table->json('event_data')->nullable();

            // Actor que causó el evento
            $table->string('causer_id')->nullable();
            $table->string('causer_name_snapshot')->nullable();
            $table->string('causer_type', 20)->default('system');

            // Solo aplica al evento contact_registered
            $table->string('contact_channel', 20)->nullable();

            // Inmutable: sin updated_at ni soft deletes
            $table->timestamp('created_at')->useCurrent();

            $table->index('tenant_id');
            $table->index('lead_id');
            $table->index(['lead_id', 'event_type']);

            $table->foreign('lead_id')
                ->references('id')
                ->on('leads')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_activity_logs');
    }
};
