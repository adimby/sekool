<?php

use App\Domain\Platform\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'academic_terms',
        'grade_levels',
        'classrooms',
        'attendance_records',
        'numbering_sequences',
        'fee_schedules',
        'fee_items',
        'payer_accounts',
        'invoices',
        'invoice_lines',
        'installments',
        'payments',
        'payment_allocations',
        'receipts',
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
