<?php

namespace Workbench\Database\Factories;

use Cachet\Database\Factories\UserFactory as CachetUserFactory;
use Illuminate\Support\Str;
use Workbench\App\User;

/**
 * Extends Cachet's factory to share its group states.
 */
class UserFactory extends CachetUserFactory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = User::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'remember_token' => Str::random(10),
            'active' => false,
        ];
    }

    /**
     * Indicate that the model's should be active.
     *
     * @return static
     */
    public function active()
    {
        return $this->state(function (array $attributes) {
            return [
                'active' => true,
            ];
        });
    }
}
