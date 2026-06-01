<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_api_clients', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');

            $table->string('name');
            $table->string('token_name')->unique();

            // Contexto del cliente: qué sistema/canal usará
            $table->string('source_system');
            $table->string('source_channel')->nullable();

            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'source_system']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_api_clients');
    }
};
