<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Groups\Group;
use App\Models\Groups\Promotion;
use App\Models\Groups\Subgroup;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GroupTest extends TestCase
{
    use RefreshDatabase;
    public function test_academic_group_creation()
    {
        // Exécuter les seeders nécessaires
        $this->seed([
            \Database\Seeders\YearSeeder::class,
            \Database\Seeders\Groups\PromotionSeeder::class,
            \Database\Seeders\Groups\GroupSeeder::class
        ]);
        
        $group = Group::first();
        echo("test");
        echo($group->promotion_id);

        $this->assertInstanceOf(Group::class, $group);
        $this->assertEquals('G1', $group->name);
        $this->assertInstanceOf(Promotion::class, $group->academicPromotion);
    }

    public function test_academic_group_relationships()
    {
        // Exécuter les seeders nécessaires
        $this->seed([
            \Database\Seeders\YearSeeder::class,
            \Database\Seeders\Groups\PromotionSeeder::class,
            \Database\Seeders\Groups\GroupSeeder::class,
            \Database\Seeders\Groups\SubgroupSeeder::class
        ]);
        
        $group = Group::with(['academicPromotion', 'academicSubgroups'])->first();

        // Test de la relation avec AcademicPromotion
        $this->assertInstanceOf(Promotion::class, $group->academicPromotion);
        $this->assertEquals('BUT1', $group->academicPromotion->name);

        // Test de la relation avec AcademicSubgroups
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $group->academicSubgroups);
        $this->assertInstanceOf(Subgroup::class, $group->academicSubgroups->first());
        $this->assertCount(2, $group->academicSubgroups); // Car G1 a deux sous-groupes (G1A et G1B)
    }

    public function test_academic_group_cascade_deletion()
    {
        // Exécuter les seeders nécessaires
        $this->seed([
            \Database\Seeders\YearSeeder::class,
            \Database\Seeders\Groups\PromotionSeeder::class,
            \Database\Seeders\Groups\GroupSeeder::class,
            \Database\Seeders\Groups\SubgroupSeeder::class
        ]);

        $group = Group::first();
        $subgroupIds = $group->academicSubgroups->pluck('id')->toArray();

        // Supprimer le groupe
        $group->delete();

        // Vérifier que le groupe a été supprimé
        $this->assertDatabaseMissing('groups', [
            'id' => $group->id
        ]);

        // Vérifier que les sous-groupes ont été supprimés en cascade
        foreach ($subgroupIds as $subgroupId) {
            $this->assertDatabaseMissing('subgroups', [
                'id' => $subgroupId
            ]);
        }
    }

    public function test_academic_group_validation()
    {
        $this->seed([
            \Database\Seeders\YearSeeder::class,
            \Database\Seeders\Groups\PromotionSeeder::class,
        ]);

        $promotion = Promotion::first();

        // Test de création avec des données valides
        $group = Group::create([
            'name' => 'Test Group',
            'promotion_id' => $promotion->id
        ]);

        $this->assertDatabaseHas('groups', [
            'name' => 'Test Group',
            'promotion_id' => $promotion->id
        ]);

        // Test de création avec une promotion inexistante
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        Group::create([
            'name' => 'Invalid Group',
            'promotion_id' => 999 // ID inexistant
        ]);
    }
}
