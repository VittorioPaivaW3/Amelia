<?php

namespace Database\Factories;

use App\Rules\Cpf;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $cpf = $this->makeCpf();

        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'cpf' => $cpf,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'role' => 'user',
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    private function makeCpf(): string
    {
        do {
            $base = str_pad((string) $this->faker->unique()->randomNumber(9, true), 9, '0', STR_PAD_LEFT);
            $digits = array_map('intval', str_split($base));

            $sum = 0;
            for ($i = 0; $i < 9; $i++) {
                $sum += $digits[$i] * (10 - $i);
            }
            $check = 11 - ($sum % 11);
            $digits[] = $check >= 10 ? 0 : $check;

            $sum = 0;
            for ($i = 0; $i < 10; $i++) {
                $sum += $digits[$i] * (11 - $i);
            }
            $check = 11 - ($sum % 11);
            $digits[] = $check >= 10 ? 0 : $check;

            $cpf = implode('', $digits);
        } while (! Cpf::isValid($cpf));

        return $cpf;
    }
}
