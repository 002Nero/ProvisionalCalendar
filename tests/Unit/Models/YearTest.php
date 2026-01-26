<?php

namespace Tests\Unit\Models;

use App\Models\Year;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\WithoutDatabaseTestCase;

class YearTest extends WithoutDatabaseTestCase
{
    public function test_fillable_fields()
    {
        $year = new Year();

        $this->assertSame(['name'], $year->getFillable());
    }

    public function test_relations_types()
    {
        $year = new Year();

        $this->assertInstanceOf(HasMany::class, $year->weeks());
        $this->assertInstanceOf(HasMany::class, $year->teachers());
        $this->assertInstanceOf(HasMany::class, $year->teachings());
        $this->assertInstanceOf(HasMany::class, $year->Promotions());
        $this->assertInstanceOf(HasMany::class, $year->alerts());
        $this->assertInstanceOf(HasMany::class, $year->semesters());
        $this->assertInstanceOf(HasMany::class, $year->trimesters());
    }

    public function test_attribute_assignment_without_mass_assignment()
    {
        $year = new Year(['name' => '2024-2025']);
        $year->periodicity = 'Semestrial';

        $this->assertSame('2024-2025', $year->name);
        $this->assertSame('Semestrial', $year->periodicity);
    }
}
