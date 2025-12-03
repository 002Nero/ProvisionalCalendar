<?php

namespace Database\Seeders\Groups;

use Illuminate\Database\Seeder;
use App\Models\Groups\Group;
use App\Models\Groups\Promotion;

class GroupSeeder extends Seeder
{
    public function run(): void
    {
        $promotions = Promotion::all();
        foreach ($promotions as $p) {
            for ($i = 1; $i <= 3; $i++) {
                $name = 'G' . $i;
                $data = ['name' => $name, 'promotion_id' => $p->id, 'student_amount' => 28];
                Group::updateOrCreate(['name' => $name, 'promotion_id' => $p->id], $data);
            }
        }
    }
}

