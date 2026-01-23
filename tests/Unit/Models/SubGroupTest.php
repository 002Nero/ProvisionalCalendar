<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Groups\Group;
use App\Models\Groups\Subgroup;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SubGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_subgroup_creation()
    {
        // Exécuter les seeders nécessaires
        $this->seed([
            \Database\Seeders\YearSeeder::class,
            \Database\Seeders\Groups\PromotionSeeder::class,
            \Database\Seeders\Groups\GroupSeeder::class,
            \Database\Seeders\Groups\SubgroupSeeder::class
        ]);

        $subgroup = Subgroup::first();

        $this->assertInstanceOf(Subgroup::class, $subgroup);
        $this->assertEquals('A', $subgroup->name);
        $this->assertInstanceOf(Group::class, $subgroup->academicGroup);
    }

    public function test_academic_subgroup_relationships()
    {
        // Exécuter les seeders nécessaires
        $this->seed([
            \Database\Seeders\YearSeeder::class,
            \Database\Seeders\Groups\PromotionSeeder::class,
            \Database\Seeders\Groups\GroupSeeder::class,
            \Database\Seeders\Groups\SubgroupSeeder::class
        ]);
        
        $subgroup = Subgroup::with('academicGroup.academicPromotion')->first();

        // Test de la relation avec AcademicGroup
        $this->assertInstanceOf(Group::class, $subgroup->academicGroup);
        $this->assertEquals('G1', $subgroup->academicGroup->name);

        // Test de la relation imbriquée avec AcademicPromotion
        $this->assertEquals('DUT1', $subgroup->academicGroup->academicPromotion->name);
    }

    public function test_academic_subgroup_validation()
    {
        $this->seed([
            \Database\Seeders\YearSeeder::class,
            \Database\Seeders\Groups\PromotionSeeder::class,
            \Database\Seeders\Groups\GroupSeeder::class
        ]);

        $group = Group::first();

        // Test de création avec des données valides
        $subgroup = Subgroup::create([
            'name' => 'Test Subgroup',
            'group_id' => $group->id
        ]);

        $this->assertDatabaseHas('subgroups', [
            'name' => 'Test Subgroup',
            'group_id' => $group->id
        ]);

        // Test de création avec un groupe inexistant
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        Subgroup::create([
            'name' => 'Invalid Subgroup',
            'group_id' => 999 // ID inexistant
        ]);
    }
}
