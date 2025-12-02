<?php

namespace Database\Factories;

use App\Models\SlotType;
use Illuminate\Database\Eloquent\Factories\Factory;

class SlotTypeFactory extends Factory
{
    protected $model = \App\Models\SlotType::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->word(),
            'acronym' => strtoupper($this->faker->lexify('??')),
            'slot_order' => $this->faker->numberBetween(1, 10),
            'color' => '#'.substr(md5($this->faker->unique()->word()), 0, 6),
        ];
    }
}
