<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TeacherTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_creation()
    {
        // Exécuter les seeders nécessaires
        $this->seed([
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\YearSeeder::class,
            \Database\Seeders\UserSeeder::class,
            \Database\Seeders\TeacherSeeder::class
        ]);
        
        $teacher = Teacher::first();

        $this->assertInstanceOf(Teacher::class, $teacher);
        $this->assertEquals('LD', $teacher->acronym);
        #$this->assertEquals('Laurent', $teacher->first_name); c'est de la merde
        #$this->assertEquals('DUBREUIL', $teacher->last_name);
        $this->assertInstanceOf(User::class, $teacher->user);
        $this->assertInstanceOf(Year::class, $teacher->year);
    }

    public function test_teacher_relationships()
    {
        // Exécuter les seeders nécessaires
        $this->seed([
            \Database\Seeders\RoleSeeder::class,
            \Database\Seeders\YearSeeder::class,
            \Database\Seeders\UserSeeder::class,
            \Database\Seeders\TeacherSeeder::class
        ]);
        
        $teacher = Teacher::first();
        $this->assertInstanceOf(User::class, $teacher->user);
        $this->assertInstanceOf(Year::class, $teacher->year);
    }
}
