<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('requires school_id, a leading index, and RLS on every tenant table', function () {
    $tenantTables = ['school_years', 'school_role_assignments'];

    foreach ($tenantTables as $table) {
        expect(Schema::hasColumn($table, 'school_id'))->toBeTrue();

        $indexes = collect(DB::select(
            'select indexdef from pg_indexes where tablename = ?',
            [$table],
        ))->pluck('indexdef')->implode(' ');

        expect($indexes)->toContain('school_id');

        $rls = DB::selectOne(
            'select c.relrowsecurity as enabled, c.relforcerowsecurity as forced
             from pg_class c
             join pg_namespace n on n.oid = c.relnamespace
             where n.nspname = ? and c.relname = ?',
            ['public', $table],
        );

        expect((bool) $rls->enabled)->toBeTrue()
            ->and((bool) $rls->forced)->toBeTrue();
    }
});

it('does not put school_id on platform tables', function () {
    expect(Schema::hasColumn('persons', 'school_id'))->toBeFalse()
        ->and(Schema::hasColumn('user_accounts', 'school_id'))->toBeFalse();
});
