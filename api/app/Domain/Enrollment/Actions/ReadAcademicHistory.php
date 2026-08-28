<?php

namespace App\Domain\Enrollment\Actions;

use App\Domain\Consent\ActiveConsent;
use App\Domain\Consent\Enums\ConsentScope;
use App\Domain\Enrollment\Models\Enrollment;
use App\Domain\Identity\Models\SchoolPersonLink;
use App\Domain\Platform\Audit\Auditor;
use App\Domain\Platform\Exceptions\DomainException;
use App\Domain\Platform\Tenancy\TenantContext;
use Illuminate\Support\Collection;

final class ReadAcademicHistory
{
    /**
     * @return array{own: Collection<int, Enrollment>, shared: Collection<int, Enrollment>}
     */
    public function forSchool(string $schoolId, string $personId): array
    {
        $linked = SchoolPersonLink::query()
            ->where('school_id', $schoolId)
            ->where('person_id', $personId)
            ->exists();

        if (! $linked) {
            throw new DomainException('Not found.', 404);
        }

        $own = Enrollment::query()
            ->where('person_id', $personId)
            ->orderBy('enrolled_on')
            ->get();

        $shared = collect();

        if (ActiveConsent::exists($personId, $schoolId, ConsentScope::AcademicRecords)) {
            $shared = TenantContext::runWithRlsBypass(fn () => Enrollment::query()
                ->withoutGlobalScopes()
                ->where('person_id', $personId)
                ->where('school_id', '!=', $schoolId)
                ->orderBy('enrolled_on')
                ->get());
        }

        Auditor::record('person.academic_history_read', 'person', $personId, $personId, [
            'shared_disclosed' => $shared->isNotEmpty(),
        ]);

        return compact('own', 'shared');
    }
}
