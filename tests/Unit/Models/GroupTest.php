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
        $data = $this->createStandardAcademicStructure();
        $group = $data['group'];

        $this->assertInstanceOf(Group::class, $group);
        $this->assertEquals(self::GROUP_NAME, $group->name);
        $this->assertInstanceOf(Promotion::class, $group->Promotion);
    }

    public function test_academic_group_relationships()
    {
        $data = $this->createStandardAcademicStructure();
        $group = $data['group'];

        Subgroup::create([
            'name' => 'B',
            'group_id' => $group->id,
        ]);

        $group = Group::with(['Promotion', 'Subgroups'])->find($group->id);

        $this->assertInstanceOf(Promotion::class, $group->Promotion);
        $this->assertEquals(self::PROMOTION_NAME, $group->Promotion->name);

        $this->assertInstanceOf('Illuminate\Database\Eloquent\Collection', $group->Subgroups);
        $this->assertInstanceOf(Subgroup::class, $group->Subgroups->first());
        $this->assertCount(2, $group->Subgroups);
    }

    public function test_academic_group_validation()
    {
        $data = $this->createStandardAcademicStructure();
        $promotion = $data['promotion'];

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
