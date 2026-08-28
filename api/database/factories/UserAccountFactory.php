<?php

namespace Database\Factories;

use App\Domain\Identity\Models\Person;
use App\Domain\Identity\Models\UserAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UserAccount>
 */
class UserAccountFactory extends Factory
{
    protected $model = UserAccount::class;

    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'must_change_password' => false,
        ];
    }
}
