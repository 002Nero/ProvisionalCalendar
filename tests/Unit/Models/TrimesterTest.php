<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Trimester;
use App\Models\Year;
use App\Models\Teaching;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TrimesterTest extends TestCase
{
    use RefreshDatabase;

    public function test_trimester_creation()
    {
        $year = Year::create([
            'name' => '2024-2025',
        ]);

        $trimester = Trimester::create([
            'trimester_number' => 1,
            'year_id' => $year->id
        ]);

        $this->assertInstanceOf(Trimester::class, $trimester);
        $this->assertEquals(1, $trimester->trimester_number);
        $this->assertInstanceOf(Year::class, $trimester->year);
    }

    public function test_trimester_relationships()
    {
        $year = Year::create([
            'name' => '2024-2025',
        ]);

        $trimester = Trimester::create([
            'trimester_number' => 1,
            'year_id' => $year->id
        ]);

        $teaching = Teaching::create([
            'title' => 'Test Teaching',
            'apogee_code' => 'TEST_001',
            'tp_hours_initial' => 10.00,
            'td_hours_initial' => 10.00,
            'cm_hours' => 10.00,
            'trimester_id' => $trimester->id,
        ]);

        $trimester = Trimester::with(['year', 'teachings'])->find($trimester->id);

        // Test de la relation avec Year
        $this->assertInstanceOf(Year::class, $trimester->year);

        // Test de la relation avec Teaching
        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $trimester->teachings);
        $this->assertInstanceOf(Teaching::class, $trimester->teachings->first());
    }

    public function test_trimester_validation()
    {
        $year = Year::create([
            'name' => '2024-2025',
        ]);

        // Test de création avec des données valides
        $trimester = Trimester::create([
            'trimester_number' => 1,
            'year_id' => $year->id
        ]);

        $this->assertDatabaseHas('trimesters', [
            'trimester_number' => 1,
            'year_id' => $year->id
        ]);

        // Test de création avec une année inexistante
        $this->expectException(\Illuminate\Database\QueryException::class);

        Trimester::create([
            'trimester_number' => 1,
            'year_id' => 999 // ID inexistant
        ]);
    }
}
