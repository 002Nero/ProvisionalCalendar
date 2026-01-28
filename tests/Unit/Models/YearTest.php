<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Year;
use App\Models\Semester;
use App\Models\Groups\Promotion;
use Illuminate\Foundation\Testing\RefreshDatabase;

class YearTest extends TestCase
{
    use RefreshDatabase;

    public function test_year_creation()
    {
        $year = Year::create([
            'name' => '2020-2021',
        ]);

        $this->assertInstanceOf(Year::class, $year);
        $this->assertEquals('2020-2021', $year->name);
    }

    public function test_year_relationships()
    {
        $year = Year::create([
            'name' => self::YEAR_NAME,
        ]);

        Semester::create([
            'semester_number' => 1,
            'year_id' => $year->id,
        ]);

        Promotion::create([
            'name' => self::PROMOTION_NAME,
            'year_id' => $year->id,
        ]);

        // Test relations
        $this->assertNotNull($year->semesters);
        $this->assertInstanceOf(Semester::class, $year->semesters->first());
        $this->assertNotNull($year->Promotions);
        $this->assertInstanceOf(Promotion::class, $year->Promotions->first());
    }

    public function test_year_validation()
    {
        Year::create([
            'name' => '2025-2026',
        ]);

        $this->assertDatabaseHas('years', [
            'name' => '2025-2026',
        ]);
    }
}
