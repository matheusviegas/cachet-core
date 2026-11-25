<?php

namespace Cachet\Database\Factories;

use Cachet\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Group>
 */
class GroupFactory extends Factory
{
    protected $model = Group::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'permissions' => [],
        ];
    }

    /**
     * @param  list<string>  $permissions
     */
    public function withPermissions(array $permissions): self
    {
        return $this->state(['permissions' => $permissions]);
    }
}
