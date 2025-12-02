<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Teaching extends Model
{
    protected $fillable = [
        'title',
        'apogee_code',
        'tp_hours_initial',
        'tp_hours_continued',
        'td_hours_initial',
        'td_hours_continued',
        'cm_hours',
        'semester_id',
        'year_id'
    ];

    protected static function boot()
    {
        parent::boot();

        static::updated(function ($teaching) {
            foreach ($teaching->teachers as $teacher) {
                app(\App\Services\TeacherNotificationService::class)->handleModification($teacher);
            }
        });
    }

    public function teachers()
    {
        return $this->belongsToMany(Teacher::class, 'teachers_teachings');
    }

    public function year()
    {
        return $this->belongsTo(Year::class);
    }

    public function slots()
    {
        return $this->hasMany(Slot::class);
    }

    public function semester()
    {
        return $this->belongsTo(Semester::class);
    }

    public function trimester()
    {
        return $this->belongsTo(Trimester::class);
    }
}
