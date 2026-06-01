<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Cambia personal_access_tokens.tokenable_id de bigInteger a varchar(255)
 * para soportar tanto modelos con UUID PK (TenantApiClient) como con
 * auto-increment (User). MySQL acepta integer values como strings en varchar.
 */
return new class extends Migration
{
    public function up(): void
    {
        // SQLite es dinámicamente tipado — acepta UUID strings en columnas bigint sin cambios
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE personal_access_tokens DROP INDEX personal_access_tokens_tokenable_type_tokenable_id_index');
        DB::statement('ALTER TABLE personal_access_tokens MODIFY COLUMN tokenable_id VARCHAR(255) NOT NULL');
        DB::statement('ALTER TABLE personal_access_tokens ADD INDEX personal_access_tokens_tokenable_type_tokenable_id_index (tokenable_type, tokenable_id)');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement('ALTER TABLE personal_access_tokens DROP INDEX personal_access_tokens_tokenable_type_tokenable_id_index');
        DB::statement('ALTER TABLE personal_access_tokens MODIFY COLUMN tokenable_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE personal_access_tokens ADD INDEX personal_access_tokens_tokenable_type_tokenable_id_index (tokenable_type, tokenable_id)');
    }
};
