<?php

namespace Tests\Unit\Models;

use App\Models\Groups\Group;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\WithoutDatabaseTestCase;

class GroupTest extends WithoutDatabaseTestCase
{
    public function test_fillable_fields()
    {
        $group = new Group();

        $this->assertSame(['name', 'promotion_id'], $group->getFillable());
    }

    public function test_relations_types()
    {
        $group = new Group();

        $this->assertInstanceOf(BelongsTo::class, $group->Promotion());
        $this->assertInstanceOf(HasMany::class, $group->Subgroups());
        $this->assertInstanceOf(HasMany::class, $group->slots());
    }
}
