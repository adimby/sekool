<?php

use App\Domain\Platform\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        RowLevelSecurity::enable('school_years');

        DB::statement('ALTER TABLE school_role_assignments ENABLE ROW LEVEL SECURITY');
        DB::statement('ALTER TABLE school_role_assignments FORCE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON school_role_assignments');
        DB::statement(<<<'SQL'
            CREATE POLICY tenant_isolation ON school_role_assignments
            USING (
                current_setting('app.rls_bypass', true) = 'on'
                OR school_id = NULLIF(current_setting('app.current_school_id', true), '')::uuid
                OR person_id = NULLIF(current_setting('app.current_person_id', true), '')::uuid
            )
            WITH CHECK (
                current_setting('app.rls_bypass', true) = 'on'
                OR school_id = NULLIF(current_setting('app.current_school_id', true), '')::uuid
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON school_role_assignments');
        DB::statement('ALTER TABLE school_role_assignments DISABLE ROW LEVEL SECURITY');
        DB::statement('DROP POLICY IF EXISTS tenant_isolation ON school_years');
        DB::statement('ALTER TABLE school_years DISABLE ROW LEVEL SECURITY');
    }
};
