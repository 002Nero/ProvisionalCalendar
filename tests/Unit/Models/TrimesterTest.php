<?php

namespace Tests\Unit\Models;

use App\Models\Trimester;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\WithoutDatabaseTestCase;

class TrimesterTest extends WithoutDatabaseTestCase
{
    public function test_fillable_fields()
    {
        $trimester = new Trimester();

        $this->assertSame(['trimester_number', 'year_id'], $trimester->getFillable());
    }

    public function test_relations_types()
    {
        $trimester = new Trimester();

        $this->assertInstanceOf(BelongsTo::class, $trimester->year());
        $this->assertInstanceOf(HasMany::class, $trimester->teachings());
    }
}
