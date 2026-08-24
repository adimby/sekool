<?php

use App\Domain\Platform\Tenancy\RowLevelSecurity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['school_person_links', 'parent_invitations', 'enrollment_status_changes'] as $table) {
            RowLevelSecurity::enable($table);
        }

        $this->policy('person_link_requests', <<<'SQL'
            current_setting('app.rls_bypass', true) = 'on'
            OR school_id = NULLIF(current_setting('app.current_school_id', true), '')::uuid
            OR matched_person_id = NULLIF(current_setting('app.current_person_id', true), '')::uuid
        SQL);

        $this->policy('enrollments', <<<'SQL'
            current_setting('app.rls_bypass', true) = 'on'
            OR school_id = NULLIF(current_setting('app.current_school_id', true), '')::uuid
            OR (
                NULLIF(current_setting('app.current_person_id', true), '') IS NOT NULL
                AND EXISTS (
                    SELECT 1 FROM relationships r
                    WHERE r.subject_person_id = NULLIF(current_setting('app.current_person_id', true), '')::uuid
                      AND r.object_person_id = enrollments.person_id
                      AND r.status = 'active'
                      AND r.type IN ('parent_of', 'guardian_of')
                )
            )
        SQL);

        $this->policy('enrollment_transfers', <<<'SQL'
            current_setting('app.rls_bypass', true) = 'on'
            OR origin_school_id = NULLIF(current_setting('app.current_school_id', true), '')::uuid
            OR destination_school_id = NULLIF(current_setting('app.current_school_id', true), '')::uuid
            OR (
                NULLIF(current_setting('app.current_person_id', true), '') IS NOT NULL
                AND EXISTS (
                    SELECT 1 FROM relationships r
                    WHERE r.subject_person_id = NULLIF(current_setting('app.current_person_id', true), '')::uuid
                      AND r.object_person_id = enrollment_transfers.person_id
                      AND r.status = 'active'
                      AND r.type IN ('parent_of', 'guardian_of')
                )
            )
        SQL);

        $this->policy('documents', <<<'SQL'
            current_setting('app.rls_bypass', true) = 'on'
            OR school_id = NULLIF(current_setting('app.current_school_id', true), '')::uuid
            OR (
                school_id IS NULL
                AND NULLIF(current_setting('app.current_school_id', true), '') IS NOT NULL
                AND EXISTS (
                    SELECT 1 FROM consents c
                    WHERE c.subject_person_id = documents.owner_person_id
                      AND c.grantee_school_id = NULLIF(current_setting('app.current_school_id', true), '')::uuid
                      AND c.scope IN ('documents.external', 'documents.certificates')
                      AND c.revoked_at IS NULL
                      AND c.expires_at > now()
                )
            )
            OR (
                NULLIF(current_setting('app.current_person_id', true), '') IS NOT NULL
                AND (
                    owner_person_id = NULLIF(current_setting('app.current_person_id', true), '')::uuid
                    OR uploaded_by_person_id = NULLIF(current_setting('app.current_person_id', true), '')::uuid
                    OR EXISTS (
                        SELECT 1 FROM relationships r
                        WHERE r.subject_person_id = NULLIF(current_setting('app.current_person_id', true), '')::uuid
                          AND r.object_person_id = documents.owner_person_id
                          AND r.status = 'active'
                          AND r.type IN ('parent_of', 'guardian_of')
                    )
                )
            )
        SQL);

        $this->policy('consents', <<<'SQL'
            current_setting('app.rls_bypass', true) = 'on'
            OR grantee_school_id = NULLIF(current_setting('app.current_school_id', true), '')::uuid
            OR granted_by_person_id = NULLIF(current_setting('app.current_person_id', true), '')::uuid
            OR subject_person_id = NULLIF(current_setting('app.current_person_id', true), '')::uuid
        SQL);

        $this->policy('consent_events', <<<'SQL'
            current_setting('app.rls_bypass', true) = 'on'
            OR EXISTS (
                SELECT 1 FROM consents c
                WHERE c.id = consent_events.consent_id
                  AND (
                    c.grantee_school_id = NULLIF(current_setting('app.current_school_id', true), '')::uuid
                    OR c.granted_by_person_id = NULLIF(current_setting('app.current_person_id', true), '')::uuid
                    OR c.subject_person_id = NULLIF(current_setting('app.current_person_id', true), '')::uuid
                  )
            )
        SQL);

        $this->policy('audit_events', <<<'SQL'
            current_setting('app.rls_bypass', true) = 'on'
            OR actor_school_id = NULLIF(current_setting('app.current_school_id', true), '')::uuid
            OR actor_person_id = NULLIF(current_setting('app.current_person_id', true), '')::uuid
            OR subject_person_id = NULLIF(current_setting('app.current_person_id', true), '')::uuid
            OR (
                NULLIF(current_setting('app.current_person_id', true), '') IS NOT NULL
                AND EXISTS (
                    SELECT 1 FROM relationships r
                    WHERE r.subject_person_id = NULLIF(current_setting('app.current_person_id', true), '')::uuid
                      AND r.object_person_id = audit_events.subject_person_id
                      AND r.status = 'active'
                      AND r.type IN ('parent_of', 'guardian_of')
                )
            )
        SQL);
    }

    public function down(): void
    {
        foreach ([
            'audit_events', 'consent_events', 'consents', 'documents',
            'enrollment_transfers', 'enrollments', 'enrollment_status_changes',
            'parent_invitations', 'person_link_requests', 'school_person_links',
        ] as $table) {
            DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
            DB::statement("ALTER TABLE {$table} DISABLE ROW LEVEL SECURITY");
        }
    }

    private function policy(string $table, string $predicate): void
    {
        DB::statement("ALTER TABLE {$table} ENABLE ROW LEVEL SECURITY");
        DB::statement("ALTER TABLE {$table} FORCE ROW LEVEL SECURITY");
        DB::statement("DROP POLICY IF EXISTS tenant_isolation ON {$table}");
        DB::statement("
            CREATE POLICY tenant_isolation ON {$table}
            USING ({$predicate})
            WITH CHECK ({$predicate})
        ");
    }
};
