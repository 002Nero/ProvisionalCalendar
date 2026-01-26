<?php

namespace Tests\Unit\Models;

use App\Models\Teacher;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Tests\WithoutDatabaseTestCase;

class TeacherTest extends WithoutDatabaseTestCase
{
    public function test_fillable_fields()
    {
        $teacher = new Teacher();

        $this->assertSame(['user_id', 'acronym', 'first_name', 'last_name', 'year_id'], $teacher->getFillable());
    }

    public function test_relations_types()
    {
        $teacher = new Teacher();

        $this->assertInstanceOf(BelongsTo::class, $teacher->user());
        $this->assertInstanceOf(BelongsToMany::class, $teacher->teachings());
        $this->assertInstanceOf(BelongsTo::class, $teacher->year());
        $this->assertInstanceOf(BelongsToMany::class, $teacher->slots());
    }
}
