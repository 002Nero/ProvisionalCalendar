<?php

namespace Tests\Unit\Models;

use App\Models\Groups\Promotion;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\WithoutDatabaseTestCase;

class PromotionTest extends WithoutDatabaseTestCase
{
    public function test_fillable_fields()
    {
        $promotion = new Promotion();

        $this->assertSame(['name', 'year_id'], $promotion->getFillable());
    }

    public function test_relations_types()
    {
        $promotion = new Promotion();

        $this->assertInstanceOf(HasMany::class, $promotion->Groups());
        $this->assertInstanceOf(BelongsTo::class, $promotion->year());
    }
}
