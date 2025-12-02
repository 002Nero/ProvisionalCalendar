<?php

namespace Database\Seeders;

use App\Models\Teacher;
use App\Models\Teaching;
use Illuminate\Database\Seeder;

class TeacherTeachingSeeder extends Seeder
{
    public function run(): void
    {
        $pairs = [
            ['acronym' => 'LD', 'apogee' => 'R1.05'],
            ['acronym' => 'TM', 'apogee' => 'R2.01'],
            ['acronym' => 'NM', 'apogee' => 'R3.01'],
            ['acronym' => 'IB', 'apogee' => 'R1.02'],
            ['acronym' => 'AL', 'apogee' => 'R1.03'],
        ];

        foreach ($pairs as $p) {
            $teacher = \App\Models\Teacher::whereHas('user', function($q) use ($p) { $q->where('acronym', $p['acronym']); })->first();
            $teaching = \App\Models\Teaching::where('apogee_code', $p['apogee'])->first();

            if ($teacher && $teaching) {
                $teacher->teachings()->syncWithoutDetaching([$teaching->id]);
            }
        }
    }
}
