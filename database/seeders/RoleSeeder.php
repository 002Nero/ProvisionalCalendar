<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            ['level' => 0, 'name' => 'Admin'],
            ['level' => 1, 'name' => 'Teacher'],
            ['level' => 2, 'name' => 'Student'],
        ];

        foreach ($roles as $r) {
            Role::updateOrCreate(['level' => $r['level']], $r);
        }
    }
}
