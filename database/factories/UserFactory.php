<?php

namespace Cachet\Database\Factories;

use Cachet\Enums\DefaultGroupEnum;
use Cachet\Models\Group;
use Cachet\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

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
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Add the user to a default group.
     */
    public function inDefaultGroup(DefaultGroupEnum $key): static
    {
        return $this->afterCreating(fn (User $user) => $user->groups()->attach(Group::findDefault($key)));
    }

    public function administrator(): static
    {
        return $this->inDefaultGroup(DefaultGroupEnum::administrators);
    }

    /**
     * Add the user to a new group granting the given permissions.
     *
     * @param  list<string>  $permissions
     */
    public function withPermissions(array $permissions): static
    {
        return $this->afterCreating(fn (User $user) => $user->groups()->attach(
            Group::factory()->withPermissions($permissions)->create(),
        ));
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
}
