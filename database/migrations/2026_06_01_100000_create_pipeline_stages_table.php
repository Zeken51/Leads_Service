<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pipeline_stages', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id')->index();

            $table->string('name');
            $table->string('slug');
            $table->unsignedSmallInteger('order')->default(0);
            $table->string('color', 7)->default('#6B7280');

            $table->boolean('is_initial')->default(false);
            $table->boolean('is_terminal')->default(false);
            // 'won' | 'lost' | null — solo válido cuando is_terminal=true
            $table->string('maps_to_status', 20)->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['tenant_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pipeline_stages');
    }
};
