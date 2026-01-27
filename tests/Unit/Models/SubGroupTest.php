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
        $year = Year::create([
            'name' => '2024-2025',
        ]);

        $promotion = Promotion::create([
            'name' => 'DUT1',
            'year_id' => $year->id,
        ]);

        $group = Group::create([
            'name' => 'G1',
            'promotion_id' => $promotion->id,
        ]);

        $subgroup = Subgroup::create([
            'name' => 'A',
            'group_id' => $group->id,
        ]);

        $this->assertInstanceOf(Subgroup::class, $subgroup);
        $this->assertEquals('A', $subgroup->name);
        $this->assertInstanceOf(Group::class, $subgroup->group);
    }

    public function test_academic_subgroup_relationships()
    {
        $year = Year::create([
            'name' => '2024-2025',
        ]);

        $promotion = Promotion::create([
            'name' => 'DUT1',
            'year_id' => $year->id,
        ]);

        $group = Group::create([
            'name' => 'G1',
            'promotion_id' => $promotion->id,
        ]);

        $subgroup = Subgroup::create([
            'name' => 'A',
            'group_id' => $group->id,
        ]);

        $subgroup = Subgroup::with('group.Promotion')->find($subgroup->id);

        $this->assertInstanceOf(Group::class, $subgroup->group);
        $this->assertEquals('G1', $subgroup->group->name);

        $this->assertEquals('DUT1', $subgroup->group->Promotion->name);
    }

    public function test_academic_subgroup_validation()
    {
        $year = Year::create([
            'name' => '2024-2025',
        ]);

        $promotion = Promotion::create([
            'name' => 'DUT1',
            'year_id' => $year->id,
        ]);

        $group = Group::create([
            'name' => 'G1',
            'promotion_id' => $promotion->id,
        ]);

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
