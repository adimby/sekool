<?php

namespace App\Domain\School\Actions;

use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Tenancy\TenantContext;
use App\Domain\School\Models\School;
use App\Domain\School\Models\SchoolYear;
use Illuminate\Support\Str;

final class ProvisionSchoolFromPlatform
{
    /**
     * @param  array<string, mixed>  $directory
     * @param  array{first_name: string, last_name: string, email: string, password?: string}|null  $admin
     * @return array{school: School, temporary_password?: string}
     */
    public function execute(array $directory, ?array $admin = null): array
    {
        return TenantContext::runWithRlsBypass(function () use ($directory, $admin): array {
            $school = School::query()->create([
                'name' => $directory['name'],
                'short_name' => $directory['short_name'] ?? null,
                'code' => $directory['code'] ?? $this->uniqueCode($directory['name']),
                'city' => $directory['city'] ?? null,
                'region' => $directory['region'] ?? null,
                'phone_e164' => $directory['phone_e164'] ?? null,
                'email' => $directory['email'] ?? null,
                'timezone' => $directory['timezone'] ?? 'Indian/Antananarivo',
                'currency' => 'MGA',
                'locale' => $directory['locale'] ?? 'fr',
                'status' => 'active',
                'plan' => $directory['plan'] ?? 'starter',
            ]);

            $startYear = now()->month >= 8 ? now()->year : now()->year - 1;

            SchoolYear::query()->withoutGlobalScopes()->create([
                'school_id' => $school->id,
                'label' => $startYear.'-'.($startYear + 1),
                'starts_on' => sprintf('%d-09-01', $startYear),
                'ends_on' => sprintf('%d-07-15', $startYear + 1),
                'is_current' => true,
            ]);

            $result = ['school' => $school];

            if ($admin !== null) {
                $granted = app(GrantSchoolAdminFromPlatform::class)->execute($school, $admin);
                if (isset($granted['temporary_password'])) {
                    $result['temporary_password'] = $granted['temporary_password'];
                }
            }

            Auditor::record(
                'platform.school.created',
                'school',
                $school->id,
                context: [
                    'code' => $school->code,
                    'plan' => $school->plan,
                    'city' => $school->city,
                ],
            );

            return $result;
        });
    }

    private function uniqueCode(string $name): string
    {
        $base = Str::lower((string) preg_replace('/[^a-z0-9]/', '', Str::ascii($name)));
        $base = substr($base !== '' ? $base : 'ecole', 0, 16);
        $code = $base;
        $n = 0;

        while (School::query()->where('code', $code)->exists()) {
            $n++;
            $code = $base.Str::lower(Str::random(3));
            if ($n > 20) {
                return 'e'.Str::lower(Str::random(10));
            }
        }

        return $code;
    }
}
