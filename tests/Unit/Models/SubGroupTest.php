<?php

namespace Tests\Unit\Models;

use App\Models\Groups\Subgroup;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\WithoutDatabaseTestCase;

class SubgroupTest extends WithoutDatabaseTestCase
{
    public function test_fillable_fields()
    {
        $subgroup = new Subgroup();

        $this->assertSame(['name', 'group_id'], $subgroup->getFillable());
    }

    public function test_relation_type_and_with_property()
    {
        $subgroup = new Subgroup();

        $this->assertInstanceOf(BelongsTo::class, $subgroup->Group());
        $eagerLoads = $subgroup->getEagerLoads();
        $this->assertArrayHasKey('Group', $eagerLoads);
        $this->assertArrayHasKey('Group.Promotion', $eagerLoads);
    }
}
