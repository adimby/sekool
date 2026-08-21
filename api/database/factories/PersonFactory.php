<?php

namespace Database\Factories;

use App\Domain\Identity\Enums\BirthDatePrecision;
use App\Domain\Identity\Enums\Sex;
use App\Domain\Identity\Models\Person;
use App\Domain\Identity\PublicId\FanabePublicId;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Person>
 */
class PersonFactory extends Factory
{
    protected $model = Person::class;

    public function definition(): array
    {
        return [
            'public_id' => FanabePublicId::generate()->canonical(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'birth_date' => fake()->dateTimeBetween('-50 years', '-5 years')->format('Y-m-d'),
            'birth_date_precision' => BirthDatePrecision::Exact,
            'sex' => fake()->randomElement(Sex::cases()),
            'preferred_language' => 'fr',
        ];
    }
}
