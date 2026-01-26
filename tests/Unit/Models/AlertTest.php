<?php

namespace Tests\Unit\Models;

use App\Models\Alert;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\WithoutDatabaseTestCase;

class AlertTest extends WithoutDatabaseTestCase
{
    public function test_fillable_fields()
    {
        $alert = new Alert();

        $this->assertSame(['message', 'year_id'], $alert->getFillable());
    }

    public function test_relations_types()
    {
        $alert = new Alert();

        $this->assertInstanceOf(BelongsTo::class, $alert->year());
    }
}
