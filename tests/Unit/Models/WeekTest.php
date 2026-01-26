<?php

namespace Tests\Unit\Models;

use App\Models\Week;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\WithoutDatabaseTestCase;

class WeekTest extends WithoutDatabaseTestCase
{
    public function test_fillable_fields()
    {
        $week = new Week();

        $this->assertSame(['name', 'week_number', 'year_id'], $week->getFillable());
    }

    public function test_relations_types()
    {
        $week = new Week();

        $this->assertInstanceOf(BelongsTo::class, $week->year());
        $this->assertInstanceOf(HasMany::class, $week->slots());
    }
}
