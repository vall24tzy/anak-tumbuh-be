<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    // Disesuaikan ke skema users final (role/username/password) — DB Design v1.5 §5.1.
    // Dipakai oleh factory Teacher/Headmaster/Student/dsb. di task masing-masing
    // (mis. ->for(User::factory()->state(['role' => 'student']))).
    public function definition(): array
    {
        return [
            'role' => 'student',
            'username' => fake()->unique()->numerify('##########'),
            'password' => static::$password ??= Hash::make('password'),
        ];
    }
}
