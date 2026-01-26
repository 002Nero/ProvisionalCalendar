<?php

namespace Tests\Unit\Models;

use App\Models\Semester;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\WithoutDatabaseTestCase;

class SemesterTest extends WithoutDatabaseTestCase
{
    public function test_fillable_fields()
    {
        $semester = new Semester();

        $this->assertSame(['semester_number', 'year_id'], $semester->getFillable());
    }

    public function test_relations_types()
    {
        $semester = new Semester();

        $this->assertInstanceOf(BelongsTo::class, $semester->year());
        $this->assertInstanceOf(HasMany::class, $semester->teachings());
    }
}
