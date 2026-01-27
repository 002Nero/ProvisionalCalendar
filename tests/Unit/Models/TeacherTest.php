<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Teacher;
use App\Models\User;
use App\Models\Year;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

class TeacherTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_creation()
    {
        $role = Role::create([
            'name' => 'teacher',
            'level' => 1
        ]);

        $user = User::create([
            'username' => 'jdoe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'teacher@test.com',
            'password' => bcrypt('password'),
            'acronym' => 'JD',
            'role_id' => $role->id,
        ]);

        $teacherId = DB::table('teachers')->insertGetId([
            'user_id' => $user->id,
            'type' => 'permanent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $teacher = Teacher::find($teacherId);

        $this->assertInstanceOf(Teacher::class, $teacher);
        $this->assertInstanceOf(User::class, $teacher->user);
    }

    public function test_teacher_relationships()
    {
        $role = Role::create([
            'name' => 'teacher',
            'level' => 1
        ]);

        $user = User::create([
            'username' => 'jdoe',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'teacher@test.com',
            'password' => bcrypt('password'),
            'acronym' => 'JD',
            'role_id' => $role->id,
        ]);

        $teacherId = DB::table('teachers')->insertGetId([
            'user_id' => $user->id,
            'type' => 'permanent',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $teacher = Teacher::find($teacherId);

        $this->assertInstanceOf(User::class, $teacher->user);
    }
}
