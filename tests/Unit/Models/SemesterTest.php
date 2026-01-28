<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Semester;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SemesterTest extends TestCase
{
    use RefreshDatabase;

    public function test_semester_creation()
    {
        $year = Year::create([
            'name' => self::YEAR_NAME,
        ]);

        $semester = Semester::create([
            'semester_number' => 1,
            'year_id' => $year->id
        ]);

        $this->assertInstanceOf(Semester::class, $semester);
        $this->assertEquals(1, $semester->semester_number);
        $this->assertInstanceOf(Year::class, $semester->year);
    }

    public function test_semester_relationships()
    {
        $year = Year::create([
            'name' => self::YEAR_NAME,
        ]);

        $semester = Semester::create([
            'semester_number' => 1,
            'year_id' => $year->id
        ]);

        $semester = Semester::with(['year'])->find($semester->id);

        // Test de la relation avec Year
        $this->assertInstanceOf(Year::class, $semester->year);
        $this->assertEquals(self::YEAR_NAME, $semester->year->name);
    }

    public function test_semester_validation()
    {
        $year = Year::create([
            'name' => self::YEAR_NAME,
        ]);

        Semester::create([
            'semester_number' => 1,
            'year_id' => $year->id
        ]);

        $this->assertDatabaseHas('semesters', [
            'semester_number' => 1,
            'year_id' => $year->id
        ]);

        // Test de création avec une année inexistante
        $this->expectException(\Illuminate\Database\QueryException::class);

        Semester::create([
            'semester_number' => 2,
            'year_id' => 999
        ]);
    }
}
