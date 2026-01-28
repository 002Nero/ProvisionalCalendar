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
        $data = $this->createStandardAcademicStructure();
        $promotion = $data['promotion'];

        $this->assertInstanceOf(Promotion::class, $promotion);
        $this->assertEquals(self::PROMOTION_NAME, $promotion->name);
        $this->assertInstanceOf(Year::class, $promotion->Year);
    }

    public function test_academic_promotion_relationships()
    {
        $data = $this->createStandardAcademicStructure();
        $promotion = $data['promotion'];

        Group::create([
            'name' => 'G2',
            'promotion_id' => $promotion->id,
        ]);

        $promotion = Promotion::with(['Year', 'Groups'])->find($promotion->id);

        $this->assertInstanceOf(Year::class, $promotion->Year);
        $this->assertEquals(self::YEAR_NAME, $promotion->Year->name);

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $promotion->Groups);
        $this->assertInstanceOf(Group::class, $promotion->Groups->first());
        $this->assertCount(2, $promotion->Groups);
    }

    public function test_academic_promotion_validation()
    {
        $data = $this->createStandardAcademicStructure();
        $year = $data['year'];

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
