<?php

namespace Tests\Unit\Models;

use App\Models\Teacher;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Tests\WithoutDatabaseTestCase;

class UserTest extends WithoutDatabaseTestCase
{
    public function test_fillable_fields_and_hidden_and_casts()
    {
        $user = new User();

        $this->assertSame([
            'username',
            'first_name',
            'last_name',
            'email',
            'role_id',
            'password',
            'personal_password',
            'acronym',
            'suspended',
        ], $user->getFillable());

        $this->assertSame(['password', 'remember_token'], $user->getHidden());
        $this->assertSame([
            'id' => 'int',
            'password' => 'hashed',
            'suspended' => 'boolean',
        ], $user->getCasts());
        $eagerLoads = $user->getEagerLoads();
        $this->assertArrayHasKey('role', $eagerLoads);
    }

    public function test_relations_types()
    {
        $user = new User();

        $this->assertInstanceOf(BelongsTo::class, $user->role());
        $this->assertInstanceOf(HasOne::class, $user->teacher());
    }

    public function test_role_constants()
    {
        $this->assertSame('administrator', User::ROLE_ADMIN);
        $this->assertSame('reader', User::ROLE_READER);
        $this->assertSame('extended_reader', User::ROLE_EXTENDED_READER);
    }
}
