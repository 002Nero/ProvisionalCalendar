<?php

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        return [
            'level' => $this->faker->unique()->numberBetween(1, 10),
            'name' => $this->faker->unique()->word(),
        ];
    }
}

