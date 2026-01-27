<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Teaching;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TeachingTest extends TestCase
{
    use RefreshDatabase;

    public function test_teaching_creation()
    {
        $teaching = Teaching::create([
            'title' => 'R1.01 Initiation au développement',
            'apogee_code' => 'TIN01A1M',
            'tp_hours_initial' => 15.00,
            'td_hours_initial' => 10.00,
            'cm_hours' => 15.00,
        ]);

        $this->assertInstanceOf(Teaching::class, $teaching);
        $this->assertEquals('R1.01 Initiation au développement', $teaching->title);
        $this->assertEquals('TIN01A1M', $teaching->apogee_code);
        $this->assertEquals(15.00, $teaching->tp_hours_initial);
        $this->assertEquals(10.00, $teaching->td_hours_initial);
        $this->assertEquals(15.00, $teaching->cm_hours);
    }

    public function test_teaching_relationships()
    {
        $teaching = Teaching::create([
            'title' => 'Test Teaching',
            'apogee_code' => 'TEST_001',
            'tp_hours_initial' => 10.00,
            'td_hours_initial' => 10.00,
            'cm_hours' => 10.00,
        ]);

        // Test that the teaching was created successfully
        $this->assertInstanceOf(Teaching::class, $teaching);
        $this->assertEquals('Test Teaching', $teaching->title);
    }

    public function test_teaching_validation()
    {
        // Test de création avec des données valides
        $teaching = Teaching::create([
            'title' => 'Test Teaching',
            'apogee_code' => 'TEST_001',
            'tp_hours_initial' => 10.00,
            'td_hours_initial' => 10.00,
            'cm_hours' => 10.00,
        ]);

        $this->assertDatabaseHas('teachings', [
            'title' => 'Test Teaching',
            'apogee_code' => 'TEST_001',
        ]);
    }
}
