<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Groups\Group;
use App\Models\Groups\Subgroup;
use App\Models\Groups\Promotion;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SubGroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_subgroup_creation()
    {
        $data = $this->createStandardAcademicStructure();
        $subgroup = $data['subgroup'];

        $this->assertInstanceOf(Subgroup::class, $subgroup);
        $this->assertEquals(self::SUBGROUP_NAME, $subgroup->name);
        $this->assertInstanceOf(Group::class, $subgroup->group);
    }

    public function test_academic_subgroup_relationships()
    {
        $data = $this->createStandardAcademicStructure();
        $subgroup = $data['subgroup'];

        $subgroup = Subgroup::with('group.Promotion')->find($subgroup->id);

        $this->assertInstanceOf(Group::class, $subgroup->group);
        $this->assertEquals(self::GROUP_NAME, $subgroup->group->name);

        $this->assertEquals(self::PROMOTION_NAME, $subgroup->group->Promotion->name);
    }

    public function test_academic_subgroup_validation()
    {
        $data = $this->createStandardAcademicStructure();
        $group = $data['group'];

        $subgroup = Subgroup::create([
            'name' => 'Test Subgroup',
            'group_id' => $group->id
        ]);

        $this->assertDatabaseHas('subgroups', [
            'name' => 'Test Subgroup',
            'group_id' => $group->id
        ]);
    }
}
