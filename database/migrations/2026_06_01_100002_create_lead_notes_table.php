<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lead_notes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('lead_id');
            $table->uuid('tenant_id');

            $table->text('content');

            // Snapshot del autor (referencia externa, no FK)
            $table->string('author_id')->nullable();
            $table->string('author_name_snapshot')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index('lead_id');

            $table->foreign('lead_id')
                ->references('id')
                ->on('leads')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lead_notes');
    }
};
