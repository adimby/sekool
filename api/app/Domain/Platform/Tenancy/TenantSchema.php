<?php

namespace App\Domain\Platform\Tenancy;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

/**
 * Idempotent helpers for demo deploys whose Postgres volume predates later migrations.
 */
final class TenantSchema
{
    public static function ensureCompositePrimary(string $table): void
    {
        if (! preg_match('/^[a-z0-9_]+$/', $table)) {
            throw new InvalidArgumentException('Invalid table name.');
        }

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'id') || ! Schema::hasColumn($table, 'school_id')) {
            return;
        }

        $index = $table.'_school_id_id_uidx';
        DB::statement("CREATE UNIQUE INDEX IF NOT EXISTS {$index} ON {$table} (school_id, id)");
    }
}
