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
        $year = Year::create([
            'name' => '2024-2025',
        ]);

        $promotion = Promotion::create([
            'name' => 'DUT1',
            'year_id' => $year->id,
        ]);

        $this->assertInstanceOf(Promotion::class, $promotion);
        $this->assertEquals('DUT1', $promotion->name);
        $this->assertInstanceOf(Year::class, $promotion->Year);
    }

    public function test_academic_promotion_relationships()
    {
        $year = Year::create([
            'name' => '2024-2025',
        ]);

        $promotion = Promotion::create([
            'name' => 'DUT1',
            'year_id' => $year->id,
        ]);

        $group1 = Group::create([
            'name' => 'G1',
            'promotion_id' => $promotion->id,
        ]);

        $group2 = Group::create([
            'name' => 'G2',
            'promotion_id' => $promotion->id,
        ]);

        $promotion = Promotion::with(['Year', 'Groups'])->find($promotion->id);

        $this->assertInstanceOf(Year::class, $promotion->Year);
        $this->assertEquals('2024-2025', $promotion->Year->name);

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $promotion->Groups);
        $this->assertInstanceOf(Group::class, $promotion->Groups->first());
        $this->assertCount(2, $promotion->Groups);
    }

    public function test_academic_promotion_validation()
    {
        $year = Year::create([
            'name' => '2024-2025',
        ]);

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
            'year_id' => 999
        ]);
    }
}
