<?php

namespace Database\Seeders\Groups;

use Illuminate\Database\Seeder;
use App\Models\Groups\Subgroup;
use App\Models\Groups\Group;


class SubgroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $g1 = Group::where('name', 'G1')->first();
        $g2 = Group::where('name', 'G2')->first();
        $g3 = Group::where('name', 'G3')->first();
        $g4 = Group::where('name', 'G4')->first();
        $g5 = Group::where('name', 'G5')->first();
        $g6 = Group::where('name', 'G6')->first();
        $g7 = Group::where('name', 'G7')->first();

        Subgroup::create([
            'name' => 'G1A',
            'group_id' => $g1->id
        ]);
        Subgroup::create([
            'name' => 'G1B',
            'group_id' => $g1->id
        ]);
        Subgroup::create([
            'name' => 'G2A',
            'group_id' => $g2->id
        ]);
        Subgroup::create([
            'name' => 'G2B',
            'group_id' => $g2->id
        ]);
        Subgroup::create([
            'name' => 'G3A',
            'group_id' => $g3->id
        ]);
        Subgroup::create([
            'name' => 'G3B',
            'group_id' => $g3->id
        ]);
        Subgroup::create([
            'name' => 'G4A',
            'group_id' => $g4->id
        ]);
        Subgroup::create([
            'name' => 'G4B',
            'group_id' => $g4->id
        ]);
        Subgroup::create([
            'name' => 'G5A',
            'group_id' => $g5->id
        ]);
        Subgroup::create([
            'name' => 'G5B',
            'group_id' => $g5->id
        ]);
        Subgroup::create([
            'name' => 'G6A',
            'group_id' => $g6->id
        ]);
        Subgroup::create([
            'name' => 'G6B',
            'group_id' => $g6->id
        ]);
        Subgroup::create([
            'name' => 'G7A',
            'group_id' => $g7->id
        ]);
        Subgroup::create([
            'name' => 'G7B',
            'group_id' => $g7->id
        ]);
    }
}
