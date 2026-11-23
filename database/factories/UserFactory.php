<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 *
 * La tabla users no tiene "name": usa firstname/lastname (apellidos) y names (nombres).
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        $faker = fake('es_ES');
        $sex = $faker->randomElement(['M', 'F']);

        return [
            'dni' => $faker->unique()->numerify('########'),
            'firstname' => $faker->lastName(),
            'lastname' => $faker->lastName(),
            'names' => $faker->firstName($sex === 'M' ? 'male' : 'female'),
            'password' => static::$password ??= Hash::make('password'),
            'datebirth' => $faker->dateTimeBetween('-55 years', '-20 years')->format('Y-m-d'),
            'cellphone' => '9' . $faker->numerify('########'),
            'sex' => $sex,
            'email' => $faker->unique()->userName() . '@demo.com',
            'email_verified_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
