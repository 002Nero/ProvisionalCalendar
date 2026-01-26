<?php

namespace Tests\Unit\Models;

use App\Models\SlotType;
use Tests\WithoutDatabaseTestCase;

class SlotTypeTest extends WithoutDatabaseTestCase
{
    public function test_fillable_fields()
    {
        $slotType = new SlotType();

        $this->assertSame(['name', 'acronym', 'slot_order', 'color'], $slotType->getFillable());
    }
}
