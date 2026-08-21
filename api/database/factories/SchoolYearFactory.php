<?php

namespace Database\Factories;

use App\Domain\School\Models\School;
use App\Domain\School\Models\SchoolYear;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SchoolYear>
 */
class SchoolYearFactory extends Factory
{
    protected $model = SchoolYear::class;

    public function definition(): array
    {
        return [
            'school_id' => School::factory(),
            'label' => '2026-2027',
            'starts_on' => '2026-09-01',
            'ends_on' => '2027-07-15',
            'is_current' => true,
        ];
    }
}
