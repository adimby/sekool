<?php

namespace App\Domain\Platform\Tenancy;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RowLevelSecurity
{
    /**
     * Enable FORCE RLS with a fail-closed policy.
     *
     * Bypass is reserved for migrations (`app.rls_bypass = on`).
     * Platform admin may read `schools` (column `id`) but not tenant dossiers.
     */
    public static function enable(string $table, string $column = 'school_id', bool $allowPlatformAdmin = false): void
    {
        if (! preg_match('/^[a-z_]+$/', $table) || ! preg_match('/^[a-z_]+$/', $column)) {
            throw new InvalidArgumentException('Invalid table or column name.');
        }

        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");

        $platform = $allowPlatformAdmin
            ? " OR current_setting('app.is_platform_admin', true) = 'on'"
            : '';

        $predicate = <<<SQL
current_setting('app.rls_bypass', true) = 'on'
OR {$column} = NULLIF(current_setting('app.current_school_id', true), '')::uuid
{$platform}
SQL;

        DB::statement("
            CREATE POLICY tenant_isolation ON {$table}
            USING ({$predicate})
            WITH CHECK ({$predicate})
        ");
    }
}
