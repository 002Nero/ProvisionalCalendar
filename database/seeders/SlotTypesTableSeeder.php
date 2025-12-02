<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SlotType;

class SlotTypesTableSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Cours Magistral', 'acronym' => 'CM', 'slot_order' => 1, 'color' => '#FDE74C'],
            ['name' => 'Travaux Dirigés', 'acronym' => 'TD', 'slot_order' => 2, 'color' => '#FFDDD2'],
            ['name' => 'Travaux Pratiques', 'acronym' => 'TP', 'slot_order' => 3, 'color' => '#809BCE'],
            ['name' => 'Projet', 'acronym' => 'SAE', 'slot_order' => 4, 'color' => '#20BF55'],
            ['name' => 'Examen', 'acronym' => 'EX', 'slot_order' => 5, 'color' => '#A26769'],
        ];

        foreach ($types as $t) {
            SlotType::updateOrCreate(['name' => $t['name']], $t);
        }
    }
}
