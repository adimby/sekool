<?php

use App\Domain\Platform\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'risk_assessments',
        'risk_factors',
        'collection_forecasts',
        'collection_tasks',
        'workflow_rules',
        'workflow_runs',
        'workflow_actions',
        'message_templates',
        'messages',
        'message_deliveries',
        'reliability_scores',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            RowLevelSecurity::enable($table);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }
};
