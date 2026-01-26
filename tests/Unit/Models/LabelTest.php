<?php

namespace Tests\Unit\Models;

use App\Models\Label;
use Tests\WithoutDatabaseTestCase;

class LabelTest extends WithoutDatabaseTestCase
{
    public function test_fillable_fields()
    {
        $label = new Label();

        $this->assertSame(['original_name', 'name'], $label->getFillable());
    }
}
