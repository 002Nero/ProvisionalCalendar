<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\Groups\Group;
use App\Models\Groups\Promotion;
use App\Models\Groups\Subgroup;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;

class GroupTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_group_creation()
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

        $this->assertInstanceOf(Group::class, $group);
        $this->assertEquals('G1', $group->name);
        $this->assertInstanceOf(Promotion::class, $group->Promotion);
    }

    public function test_academic_group_relationships()
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

        $subgroupA = Subgroup::create([
            'name' => 'A',
            'group_id' => $group->id,
        ]);

        $subgroupB = Subgroup::create([
            'name' => 'B',
            'group_id' => $group->id,
        ]);

        $group = Group::with(['Promotion', 'Subgroups'])->find($group->id);

        $this->assertInstanceOf(Promotion::class, $group->Promotion);
        $this->assertEquals('DUT1', $group->Promotion->name);

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $group->Subgroups);
        $this->assertInstanceOf(Subgroup::class, $group->Subgroups->first());
        $this->assertCount(2, $group->Subgroups);
    }

    public function test_academic_group_validation()
    {
        $year = Year::create([
            'name' => '2024-2025',
        ]);

        $promotion = Promotion::create([
            'name' => 'DUT1',
            'year_id' => $year->id,
        ]);

        $group = Group::create([
            'name' => 'Test Group',
            'promotion_id' => $promotion->id
        ]);

        $this->assertDatabaseHas('groups', [
            'name' => 'Test Group',
            'promotion_id' => $promotion->id
        ]);

        // Test de création avec une promotion inexistante
        $this->expectException(\Illuminate\Database\QueryException::class);

        Group::create([
            'name' => 'Invalid Group',
            'promotion_id' => 999
        ]);
    }
}
