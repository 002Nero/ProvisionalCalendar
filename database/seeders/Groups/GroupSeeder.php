<?php

namespace Database\Seeders\Groups;

use Illuminate\Database\Seeder;
use App\Models\Groups\Group;
use App\Models\Groups\Promotion;

class GroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $but1 = Promotion::where('name', 'BUT1')->first();
        $but2 = Promotion::where('name', 'BUT2')->first();
        $but3 = Promotion::where('name', 'BUT3')->first();
        
        echo($but1->id);
        Group::create([
            'name' => 'G1',
            'promotion_id' => $but1->id
        ]);
        Group::create([
            'name' => 'G2',
            'promotion_id' => $but1->id
        ]);
        Group::create([
            'name' => 'G3',
            'promotion_id' => $but1->id
        ]);
        Group::create([
            'name' => 'G4',
            'promotion_id' => $but2->id
        ]);
        Group::create([
            'name' => 'G5',
            'promotion_id' => $but2->id
        ]);
        Group::create([
            'name' => 'G6',
            'promotion_id' => $but3->id
        ]);
        Group::create([
            'name' => 'G7',
            'promotion_id' => $but3->id
        ]);
    }
}
