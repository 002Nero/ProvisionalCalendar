<?php

namespace Database\Seeders\Groups;

use Illuminate\Database\Seeder;
use App\Models\Groups\Group;

class GroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = [
            ['name' => 'G1', 'promotion_id' => 14, 'student_amount' => 28],
            ['name' => 'G2', 'promotion_id' => 14, 'student_amount' => 28],
            ['name' => 'G3', 'promotion_id' => 14, 'student_amount' => 28],
            ['name' => 'G4', 'promotion_id' => 15, 'student_amount' => 28],
            ['name' => 'G5', 'promotion_id' => 15, 'student_amount' => 28],
            ['name' => 'G6', 'promotion_id' => 15, 'student_amount' => 28],
            ['name' => 'G7', 'promotion_id' => 16, 'student_amount' => 28],
            ['name' => 'G8', 'promotion_id' => 16, 'student_amount' => 28],
        ];

        foreach ($groups as $g) {
            Group::updateOrCreate(['name' => $g['name'], 'promotion_id' => $g['promotion_id']], $g);
        }
    }
}

