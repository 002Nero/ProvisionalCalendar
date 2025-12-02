<?php

namespace Database\Seeders\Groups;

use Illuminate\Database\Seeder;
use App\Models\Groups\Subgroup;

class SubgroupSeeder extends Seeder
{
    public function run(): void
    {
        $subgroups = [
            ['name' => 'A', 'student_amount' => 17],
            ['name' => 'B', 'student_amount' => 17],
        ];

        foreach ($subgroups as $s) {
            Subgroup::updateOrCreate(['name' => $s['name']], $s);
        }
    }
}

