<?php

namespace Database\Seeders;

use App\Models\Slot;
use App\Models\SlotType;
use Illuminate\Database\Seeder;

class SlotSeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            // Semaine 1 - CM pour toute la promotion
            [
                'duration' => 2.0,
                'teaching_id' => 1,
                'promotion_id' => 1,
                'group_id' => null,
                'subgroup_id' => null,
                'room_amount' => 85,
                'is_neutralized' => false,
                'is_exam' => false,
                'week_id' => 1,
                'type_id' => 1, // CM
            ],

            // Semaine 2 - TD pour les groupes (G1,G2)
            [
                'duration' => 1.5,
                'teaching_id' => 1,
                'promotion_id' => 1,
                'group_id' => 1,
                'subgroup_id' => null,
                'room_amount' => 30,
                'is_neutralized' => false,
                'is_exam' => false,
                'week_id' => 2,
                'type_id' => 2, // TD
            ],
            [
                'duration' => 1.5,
                'teaching_id' => 1,
                'promotion_id' => 1,
                'group_id' => 2,
                'subgroup_id' => null,
                'room_amount' => 30,
                'is_neutralized' => false,
                'is_exam' => false,
                'week_id' => 2,
                'type_id' => 2, // TD
            ],

            // Semaine 2 - TP pour les sous-groupes
            [
                'duration' => 1.0,
                'teaching_id' => 1,
                'promotion_id' => 1,
                'group_id' => 1,
                'subgroup_id' => 1,
                'room_amount' => 15,
                'is_neutralized' => false,
                'is_exam' => false,
                'week_id' => 2,
                'type_id' => 3, // TP
            ],
            [
                'duration' => 1.0,
                'teaching_id' => 1,
                'promotion_id' => 1,
                'group_id' => 1,
                'subgroup_id' => 2,
                'room_amount' => 15,
                'is_neutralized' => false,
                'is_exam' => false,
                'week_id' => 2,
                'type_id' => 3, // TP
            ],
            [
                'duration' => 1.0,
                'teaching_id' => 1,
                'promotion_id' => 1,
                'group_id' => 2,
                'subgroup_id' => 1,
                'room_amount' => 15,
                'is_neutralized' => false,
                'is_exam' => false,
                'week_id' => 2,
                'type_id' => 3, // TP
            ],
            [
                'duration' => 1.0,
                'teaching_id' => 1,
                'promotion_id' => 1,
                'group_id' => 2,
                'subgroup_id' => 2,
                'room_amount' => 15,
                'is_neutralized' => false,
                'is_exam' => false,
                'week_id' => 2,
                'type_id' => 3, // TP
            ],

            // Autre enseignement - Semaine 3 CM + TD pour groupes
            [
                'duration' => 2.0,
                'teaching_id' => 2,
                'promotion_id' => 1,
                'group_id' => null,
                'subgroup_id' => null,
                'room_amount' => 85,
                'is_neutralized' => false,
                'is_exam' => false,
                'week_id' => 3,
                'type_id' => 1, // CM
            ],
            [
                'duration' => 1.5,
                'teaching_id' => 2,
                'promotion_id' => 1,
                'group_id' => 1,
                'subgroup_id' => null,
                'room_amount' => 30,
                'is_neutralized' => false,
                'is_exam' => false,
                'week_id' => 3,
                'type_id' => 2, // TD
            ],
            [
                'duration' => 1.5,
                'teaching_id' => 2,
                'promotion_id' => 1,
                'group_id' => 2,
                'subgroup_id' => null,
                'room_amount' => 30,
                'is_neutralized' => false,
                'is_exam' => false,
                'week_id' => 3,
                'type_id' => 2, // TD
            ],
        ];

        foreach ($entries as $entry) {
            Slot::Create($entry);
        }
    }
}


