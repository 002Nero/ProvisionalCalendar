<?php

namespace Database\Seeders\Groups;

use Illuminate\Database\Seeder;
use App\Models\Groups\Promotion;

class PromotionSeeder extends Seeder
{
    public function run(): void
    {
        $promotions = [
            ['name' => 'DUT1', 'year_id' => 1, 'student_amount' => 80],
            ['name' => 'DUT2', 'year_id' => 1, 'student_amount' => 80],
            ['name' => 'DUT1', 'year_id' => 2, 'student_amount' => 80],
            ['name' => 'DUT2', 'year_id' => 2, 'student_amount' => 80],
            ['name' => 'BUT1', 'year_id' => 3, 'student_amount' => 80],
            ['name' => 'BUT2', 'year_id' => 3, 'student_amount' => 60],
            ['name' => 'BUT3', 'year_id' => 3, 'student_amount' => 50],
            ['name' => 'BUT1', 'year_id' => 4, 'student_amount' => 80],
            ['name' => 'BUT2', 'year_id' => 4, 'student_amount' => 60],
            ['name' => 'BUT3', 'year_id' => 4, 'student_amount' => 50],
            ['name' => 'BUT1', 'year_id' => 5, 'student_amount' => 80],
            ['name' => 'BUT2', 'year_id' => 5, 'student_amount' => 60],
            ['name' => 'BUT3', 'year_id' => 5, 'student_amount' => 50],
            ['name' => 'BUT1', 'year_id' => 6, 'student_amount' => 80],
            ['name' => 'BUT2', 'year_id' => 6, 'student_amount' => 60],
            ['name' => 'BUT3', 'year_id' => 6, 'student_amount' => 50],
            ['name' => 'BUT1', 'year_id' => 7, 'student_amount' => 80],
            ['name' => 'BUT2', 'year_id' => 7, 'student_amount' => 60],
            ['name' => 'BUT3', 'year_id' => 7, 'student_amount' => 50],
            ['name' => 'BUT1', 'year_id' => 8, 'student_amount' => 80],
            ['name' => 'BUT2', 'year_id' => 8, 'student_amount' => 60],
            ['name' => 'BUT3', 'year_id' => 8, 'student_amount' => 50],
            ['name' => 'BUT1', 'year_id' => 9, 'student_amount' => 80],
            ['name' => 'BUT2', 'year_id' => 9, 'student_amount' => 60],
            ['name' => 'BUT3', 'year_id' => 9, 'student_amount' => 50],
            ['name' => 'BUT1', 'year_id' => 10, 'student_amount' => 80],
            ['name' => 'BUT2', 'year_id' => 10, 'student_amount' => 60],
            ['name' => 'BUT3', 'year_id' => 10, 'student_amount' => 50],
        ];

        foreach ($promotions as $p) {
            Promotion::updateOrCreate(
                ['name' => $p['name'], 'year_id' => $p['year_id']],
                $p
            );
        }
    }
}

