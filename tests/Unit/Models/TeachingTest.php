<?php

namespace Tests\Unit\Models;

use App\Models\Teaching;
use App\Models\Teacher;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Mockery;
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

    public function test_updated_event_notifies_teachers()
    {
        $service = Mockery::mock(\App\Services\TeacherNotificationService::class);
        $service->shouldReceive('handleModification')->twice();
        app()->instance(\App\Services\TeacherNotificationService::class, $service);

        $teaching = new Teaching();
        $teaching->setRelation('teachers', collect([new Teacher(['id' => 1]), new Teacher(['id' => 2])])) ;
        Teaching::flushEventListeners();
        Teaching::boot();

        foreach (Teaching::getEventDispatcher()->getListeners('eloquent.updated: '.Teaching::class) as $listener) {
            $listener('eloquent.updated: '.Teaching::class, [$teaching]);
        }

        $this->assertTrue(true);
    }
}
