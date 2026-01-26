<?php

namespace Tests\Unit\Models;

use App\Models\Role;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\WithoutDatabaseTestCase;

class RoleTest extends WithoutDatabaseTestCase
{
    public function test_fillable_fields()
    {
        $role = new Role();

        $this->assertSame(['level', 'name'], $role->getFillable());
    }

    public function test_users_relation_type()
    {
        $role = new Role();

        $this->assertInstanceOf(HasMany::class, $role->users());
    }
}
