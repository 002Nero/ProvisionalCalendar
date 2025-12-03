<?php

namespace Database\Seeders\Groups;

use Illuminate\Database\Seeder;
use App\Models\Groups\Subgroup;
use App\Models\Groups\Group;

class SubgroupSeeder extends Seeder
{
    public function run(): void
    {
        $groups = Group::all();
        foreach ($groups as $g) {
            $sA = ['name' => 'A', 'group_id' => $g->id, 'student_amount' => (int)floor(($g->student_amount ?? 28) / 2)];
            $sB = ['name' => 'B', 'group_id' => $g->id, 'student_amount' => (int)ceil(($g->student_amount ?? 28) / 2)];

            Subgroup::updateOrCreate(['name' => 'A', 'group_id' => $g->id], $sA);
            Subgroup::updateOrCreate(['name' => 'B', 'group_id' => $g->id], $sB);
        }
    }
}

