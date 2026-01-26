<?php

namespace Tests\Unit\Models;

use App\Models\Slot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Tests\WithoutDatabaseTestCase;

class SlotTest extends WithoutDatabaseTestCase
{
    public function test_fillable_fields()
    {
        $slot = new Slot();

        $this->assertSame([
            'duration',
            'teaching_id',
            'promotion_id',
            'group_id',
            'subgroup_id',
            'room_amount',
            'is_neutralized',
            'is_exam',
            'week_id',
            'type_id',
        ], $slot->getFillable());
    }

    public function test_relations_types()
    {
        $slot = new Slot();

        $this->assertInstanceOf(BelongsToMany::class, $slot->teachers());
        $this->assertInstanceOf(BelongsTo::class, $slot->teacher());
        $this->assertInstanceOf(BelongsTo::class, $slot->substituteTeacher());
        $this->assertInstanceOf(BelongsTo::class, $slot->teaching());
        $this->assertInstanceOf(BelongsTo::class, $slot->Promotion());
        $this->assertInstanceOf(BelongsTo::class, $slot->Group());
        $this->assertInstanceOf(BelongsTo::class, $slot->Subgroup());
        $this->assertInstanceOf(BelongsTo::class, $slot->week());
    }
}
