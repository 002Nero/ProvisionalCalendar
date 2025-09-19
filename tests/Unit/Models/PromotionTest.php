<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Groups\Promotion;
use App\Models\Groups\Group;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PromotionTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_promotion_creation()
    {
        // Exécuter les seeders nécessaires
        $this->seed([
            \Database\Seeders\YearSeeder::class,
            \Database\Seeders\Groups\PromotionSeeder::class
        ]);
        
        $promotion = Promotion::first();

        $this->assertInstanceOf(Promotion::class, $promotion);
        $this->assertEquals('BUT1', $promotion->name);
        $this->assertInstanceOf(Year::class, $promotion->year);
    }

    public function test_academic_promotion_relationships()
    {
        // Exécuter les seeders nécessaires
        $this->seed([
            \Database\Seeders\YearSeeder::class,
            \Database\Seeders\Groups\PromotionSeeder::class,
            \Database\Seeders\Groups\GroupSeeder::class
        ]);
        
        $promotion = Promotion::with('Groups')->first();

        // Test de la relation avec AcademicGroups
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $promotion->Groups);
        $this->assertInstanceOf(Group::class, $promotion->Groups->first());
        $this->assertCount(3, $promotion->Groups); // Car BUT1 a trois groupes (G1, G2, G3)
    }

    public function test_academic_promotion_cascade_deletion()
    {
        // Exécuter les seeders nécessaires
        $this->seed([
            \Database\Seeders\YearSeeder::class,
            \Database\Seeders\Groups\PromotionSeeder::class,
            \Database\Seeders\Groups\GroupSeeder::class,
            \Database\Seeders\Groups\SubgroupSeeder::class
        ]);

        $promotion = Promotion::first();
        $groupIds = $promotion->Groups->pluck('id')->toArray();

        // Supprimer la promotion
        $promotion->delete();

        // Vérifier que la promotion a été supprimée
        $this->assertDatabaseMissing('promotions', [
            'id' => $promotion->id
        ]);

        // Vérifier que les groupes ont été supprimés en cascade
        foreach ($groupIds as $groupId) {
            $this->assertDatabaseMissing('groups', [
                'id' => $groupId
            ]);
        }
    }

    public function test_academic_promotion_validation()
    {
        $this->seed([
            \Database\Seeders\YearSeeder::class
        ]);

        $year = Year::first();

        // Test de création avec des données valides
        $promotion = Promotion::create([
            'name' => 'Test Promotion',
            'year_id' => $year->id
        ]);

        $this->assertDatabaseHas('promotions', [
            'name' => 'Test Promotion',
            'year_id' => $year->id
        ]);

        // Test de création avec une année inexistante
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        Promotion::create([
            'name' => 'Invalid Promotion',
            'year_id' => 999 // ID inexistant
        ]);
    }
}
