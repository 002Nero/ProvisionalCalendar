<?php

namespace Tests\Unit\Models;

use App\Models\Room;
use Tests\WithoutDatabaseTestCase;

class RoomTest extends WithoutDatabaseTestCase
{
    public function test_fillable_fields()
    {
        $room = new Room();

        $this->assertSame(['name', 'seat_capacity', 'computer_capacity', 'exam_capacity'], $room->getFillable());
    }
}
