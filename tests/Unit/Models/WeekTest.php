<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\Week;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class WeekTest extends TestCase
{
    use RefreshDatabase;

    public function test_week_belongs_to_year()
    {
        $year = Year::create([
            'name' => self::YEAR_NAME,
        ]);

        $weekId = DB::table('weeks')->insertGetId([
            'week_number' => 1,
            'year_id' => $year->id,
            'start_date' => '2024-09-02',
            'end_date' => '2024-09-08',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $week = Week::find($weekId);

        $this->assertInstanceOf(Year::class, $week->year);
        $this->assertEquals($year->id, $week->year->id);
    }

    public function test_week_number_must_be_between_1_and_52()
    {
        $year = Year::create([
            'name' => self::YEAR_NAME,
        ]);

        $weekId = DB::table('weeks')->insertGetId([
            'week_number' => 1,
            'year_id' => $year->id,
            'start_date' => '2024-09-02',
            'end_date' => '2024-09-08',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $week = Week::find($weekId);

        $this->assertEquals(1, $week->week_number);

        $week2Id = DB::table('weeks')->insertGetId([
            'week_number' => 52,
            'year_id' => $year->id,
            'start_date' => '2025-08-25',
            'end_date' => '2025-08-31',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $week2 = Week::find($week2Id);

        $this->assertEquals(52, $week2->week_number);
    }

} 