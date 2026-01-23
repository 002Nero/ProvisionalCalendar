<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Teacher;
use App\Models\Year;
use Illuminate\Database\Seeder;

class TeacherSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            'TM' => 'permanent',
            'TH' => 'permanent',
            'CO' => 'permanent',
            'LD' => 'permanent',
            'NM' => 'permanent',
            'SM' => 'permanent',
            'IB' => 'permanent',
            'VB' => 'permanent',
            'DM' => 'permanent',
            'AL' => 'vacataire',
            'AP' => 'permanent',
        ];

        foreach ($users as $acronym => $type) {
            $user = \App\Models\User::where('acronym', $acronym)->first();
            $year = Year::first();

            if ($user && $year) {
                \App\Models\Teacher::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'user_id' => $user->id,
                        'type' => $type,
                        'acronym' => $acronym,
                        'year_id' => $year->id
                    ]
                );
            }
        }
    }
}
