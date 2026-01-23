<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Teaching;
use App\Models\Year;
use App\Models\Semester;
use App\Models\Trimester;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TeachingTest extends TestCase
{
    use RefreshDatabase;

    public function test_teaching_creation()
    {
        // Exécuter les seeders nécessaires
        $this->seed([
            \Database\Seeders\YearSeeder::class,
            \Database\Seeders\SemesterSeeder::class,
            \Database\Seeders\TeachingSeeder::class
        ]);
        
        $teaching = Teaching::skip(7)->first();

        $this->assertInstanceOf(Teaching::class, $teaching);
        echo($teaching->title);
        $this->assertEquals('R1.01 Initiation au développement', $teaching->title);
        $this->assertEquals('TIN01A1M', $teaching->apogee_code);
        $this->assertEquals(15.00, $teaching->tp_hours_initial);
        $this->assertEquals(10.00, $teaching->td_hours_initial);
        $this->assertEquals(15.00, $teaching->cm_hours);
        $this->assertInstanceOf(Year::class, $teaching->year);
    }

    public function test_teaching_relationships()
    {
        // Exécuter les seeders nécessaires
        $this->seed([
            \Database\Seeders\YearSeeder::class,
            \Database\Seeders\SemesterSeeder::class,
            \Database\Seeders\TeachingSeeder::class
        ]);
        
        $teaching = Teaching::with(['year', 'semester', 'trimester'])->first();

        // Test de la relation avec Year
        $this->assertInstanceOf(Year::class, $teaching->year);
        
        // Test de la relation avec Semester ou Trimester
        if ($teaching->semester_id) {
            $this->assertInstanceOf(Semester::class, $teaching->semester);
            $this->assertNull($teaching->trimester);
        } else {
            $this->assertInstanceOf(Trimester::class, $teaching->trimester);
            $this->assertNull($teaching->semester);
        }
    }

    public function test_teaching_validation()
    {
        $this->seed([
            \Database\Seeders\YearSeeder::class,
            \Database\Seeders\SemesterSeeder::class
        ]);

        $year = Year::first();
        $semester = Semester::first();

        // Test de création avec des données valides
        $teaching = Teaching::create([
            'title' => 'Test Teaching',
            'apogee_code' => 'TEST_001',
            'tp_hours_initial' => 10.00,
            'td_hours_initial' => 10.00,
            'cm_hours' => 10.00,
            'semester_id' => $semester->id,
            'trimester_id' => null,
            'year_id' => $year->id
        ]);

        $this->assertDatabaseHas('teachings', [
            'title' => 'Test Teaching',
            'apogee_code' => 'TEST_001',
            'year_id' => $year->id
        ]);

        // Test de création avec une année inexistante
        $this->expectException(\Illuminate\Database\QueryException::class);
        
        Teaching::create([
            'title' => 'Invalid Teaching',
            'apogee_code' => 'TEST_002',
            'tp_hours_initial' => 10.00,
            'td_hours_initial' => 10.00,
            'cm_hours' => 10.00,
            'semester_id' => $semester->id,
            'trimester_id' => null,
            'year_id' => 999 // ID inexistant
        ]);
    }
} 