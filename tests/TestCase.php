<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use App\Models\Year;
use App\Models\Groups\Promotion;
use App\Models\Groups\Group;
use App\Models\Groups\Subgroup;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase;

    const YEAR_NAME = '2024-2025';
    const PROMOTION_NAME = 'BUT1';
    const GROUP_NAME = 'G1';
    const SUBGROUP_NAME = 'A';
    const ROLE_ADMIN = 'Admin';
    const ROLE_TEACHER = 'teacher';

    protected function createStandardAcademicStructure(): array
    {
        $year = Year::create([
            'name' => self::YEAR_NAME,
        ]);

        $promotion = Promotion::create([
            'name' => self::PROMOTION_NAME,
            'year_id' => $year->id,
        ]);

        $group = Group::create([
            'name' => self::GROUP_NAME,
            'promotion_id' => $promotion->id,
        ]);

        $subgroup = Subgroup::create([
            'name' => self::SUBGROUP_NAME,
            'group_id' => $group->id,
        ]);

        return compact('year', 'promotion', 'group', 'subgroup');
    }

    protected function setUp(): void
    {
        parent::setUp();
        
    }
}
