<?php

namespace App\Domain\Platform\Tenancy;

use Illuminate\Support\Facades\DB;

/**
 * Request-scoped (and job-scoped) tenant context. Absence is a refusal, never "all tenants".
 */
final class TenantContext
{
    private static ?self $current = null;

    private function __construct(
        public readonly ?string $schoolId,
        public readonly ?string $personId,
        public readonly bool $isPlatformAdmin,
        public readonly bool $rlsBypass,
    ) {}

    public static function current(): ?self
    {
        return self::$current;
    }

    public static function schoolId(): ?string
    {
        return self::$current?->schoolId;
    }

    public static function requireSchoolId(): string
    {
        $id = self::$current?->schoolId;

        if ($id === null || $id === '') {
            throw new TenantContextRequiredException;
        }

        return $id;
    }

    public static function forSchool(string $schoolId, ?string $personId = null): self
    {
        return new self($schoolId, $personId, false, false);
    }

    public static function identifiedPerson(string $personId): self
    {
        return new self(null, $personId, false, false);
    }

    public static function platformAdmin(?string $personId = null): self
    {
        return new self(null, $personId, true, false);
    }

    public static function migrationBypass(): self
    {
        return new self(null, null, false, true);
    }

    public static function activate(self $context): void
    {
        self::$current = $context;
        $context->applyToConnection();
    }

    public static function clear(): void
    {
        self::$current = null;

        try {
            if (DB::connection()->getPdo() !== null) {
                DB::statement("SELECT set_config('app.current_school_id', '', false)");
                DB::statement("SELECT set_config('app.current_person_id', '', false)");
                DB::statement("SELECT set_config('app.is_platform_admin', 'off', false)");
                DB::statement("SELECT set_config('app.rls_bypass', 'off', false)");
            }
        } catch (\Throwable) {
            // Connection may not be open yet (or already closed).
        }
    }

    public function applyToConnection(): void
    {
        DB::statement("SELECT set_config('app.current_school_id', ?, false)", [$this->schoolId ?? '']);
        DB::statement("SELECT set_config('app.current_person_id', ?, false)", [$this->personId ?? '']);
        DB::statement("SELECT set_config('app.is_platform_admin', ?, false)", [$this->isPlatformAdmin ? 'on' : 'off']);
        DB::statement("SELECT set_config('app.rls_bypass', ?, false)", [$this->rlsBypass ? 'on' : 'off']);
    }
}
