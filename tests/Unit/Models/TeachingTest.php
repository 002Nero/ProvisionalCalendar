<?php

namespace Tests\Unit\Models;

use App\Models\Teaching;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\WithoutDatabaseTestCase;

class TeachingTest extends WithoutDatabaseTestCase
{
    public function test_fillable_fields()
    {
        $teaching = new Teaching();

        $this->assertSame([
            'title',
            'apogee_code',
            'tp_hours_initial',
            'tp_hours_continued',
            'td_hours_initial',
            'td_hours_continued',
            'cm_hours',
            'semester_id',
            'year_id',
        ], $teaching->getFillable());
    }

    public function test_relations_types()
    {
        $teaching = new Teaching();

        $this->assertInstanceOf(BelongsToMany::class, $teaching->teachers());
        $this->assertInstanceOf(BelongsTo::class, $teaching->year());
        $this->assertInstanceOf(HasMany::class, $teaching->slots());
        $this->assertInstanceOf(BelongsTo::class, $teaching->semester());
        $this->assertInstanceOf(BelongsTo::class, $teaching->trimester());
    }
}
