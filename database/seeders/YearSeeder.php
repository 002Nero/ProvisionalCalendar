<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Year;

class YearSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
       $years = [
            '2020-2021','2021-2022','2022-2023','2023-2024','2024-2025','2025-2026','2026-2027','2027-2028','2028-2029','2029-2030'
        ];

        foreach ($years as $name) {
            Year::updateOrCreate(
                ['name' => $name],
                [
                    'name' => $name,
                    'periodicity' => 'Semestrial'
                ]
            );
        }
    }
}
