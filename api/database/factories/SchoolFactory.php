<?php

namespace Database\Factories;

use App\Domain\School\Models\School;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<School>
 */
class SchoolFactory extends Factory
{
    protected $model = School::class;

    public function definition(): array
    {
        $city = fake()->randomElement(['Antananarivo', 'Toamasina', 'Fianarantsoa', 'Mahajanga']);

        return [
            'name' => 'École '.fake()->unique()->lastName(),
            'short_name' => strtoupper(fake()->lexify('???')),
            'code' => strtolower(Str::random(8)),
            'city' => $city,
            'region' => 'Analamanga',
            'timezone' => 'Indian/Antananarivo',
            'currency' => 'MGA',
            'locale' => 'fr',
            'status' => 'active',
            'plan' => 'starter',
        ];
    }
}
