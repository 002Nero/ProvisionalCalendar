<?php

namespace Tests\Unit\Models;

use App\Models\Groups\Group;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Mockery;
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

        $this->assertInstanceOf(BelongsTo::class, $group->promotion());
        $this->assertInstanceOf(HasMany::class, $group->subgroups());
        $this->assertInstanceOf(HasMany::class, $group->slots());
    }

    public function test_deleting_event_deletes_slots()
    {
        $relation = Mockery::mock(Relation::class);
        $relation->shouldReceive('delete')->once();

        $group = Mockery::mock(Group::class)->makePartial();
        $group->shouldReceive('slots')->andReturn($relation);

        Group::flushEventListeners();
        Group::boot();

        foreach (Group::getEventDispatcher()->getListeners('eloquent.deleting: '.Group::class) as $listener) {
            $listener('eloquent.deleting: '.Group::class, [$group]);
        }

        $this->assertTrue(true);
    }
}
