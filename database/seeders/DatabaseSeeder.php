<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\Groups\GroupSeeder;
use Database\Seeders\Groups\PromotionSeeder;
use Database\Seeders\Groups\SubgroupSeeder;
use Database\Seeders\Groups\ExportedGroupSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Ordre important : rôles, années, types, puis entités dépendantes
        $this->call([
            RoleSeeder::class,
            YearSeeder::class,
            SlotTypesTableSeeder::class,
            RoomsSeeder::class,
            PromotionSeeder::class,
            ExportedGroupSeeder::class,
            SubgroupSeeder::class,
            SemesterSeeder::class,
            WeekSeeder::class,
            TeachingSeeder::class,
            UserSeeder::class,
            TeacherSeeder::class,
            TeacherTeachingSeeder::class,
            SlotSeeder::class,
            LabelsSeeder::class,
        ]);
    }
}
