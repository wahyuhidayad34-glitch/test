<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password',        // di-hash oleh cast 'hashed'
            'remember_token' => Str::random(10),
            'role' => 'requester',
            'department' => 'IT',
            'is_active' => true,
        ];
    }

    public function role(string $role, ?string $department = 'IT'): static
    {
        return $this->state(fn () => ['role' => $role, 'department' => $department]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => ['email_verified_at' => null]);
    }
}
