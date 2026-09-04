<?php

namespace App\Domain\School\Actions;

use App\Domain\Platform\Audit\Auditor;
use App\Domain\School\Models\School;
use Illuminate\Validation\ValidationException;

final class UpdateSchoolDirectoryFromPlatform
{
    /**
     * @param  array<string, mixed>  $directory
     */
    public function execute(School $school, array $directory, ?string $reason = null): School
    {
        $sensitive = array_intersect_key($directory, array_flip(['status', 'plan']));

        if ($sensitive !== [] && ($reason === null || trim($reason) === '')) {
            throw ValidationException::withMessages([
                'reason' => 'Un motif est obligatoire pour changer le statut ou le plan.',
            ]);
        }

        $school->fill($directory)->save();

        Auditor::record(
            'platform.school.updated',
            'school',
            $school->id,
            context: [
                'fields' => array_keys($directory),
                'reason' => $reason,
                'status' => $school->status,
                'plan' => $school->plan,
            ],
        );

        return $school->refresh();
    }
}
