<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Groups\Promotion;
use App\Models\Groups\Group;
use App\Models\Groups\Subgroup;


class Slot extends Model
{
    protected $fillable = [
        'duration',
        'teacher_id',
        'teaching_id',
        'substitute_teacher_id',
        'academic_promotion_id',
        'academic_group_id',
        'academic_subgroup_id',
        'is_neutralized',
        'week_id',
        'type', // Ajout du type
    ];

    protected static function boot()
    {
        parent::boot();

        static::updated(function ($slot) {
            foreach ($slot->teachers as $teacher) {
                app(\App\Services\TeacherNotificationService::class)->handleModification($teacher);
            }
        });
    }

    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    public function substituteTeacher()
    {
        return $this->belongsTo(Teacher::class, 'substitute_teacher_id');
    }

    public function teaching()
    {
        return $this->belongsTo(Teaching::class);
    }

    public function academicPromotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function academicGroup()
    {
        return $this->belongsTo(Group::class);
    }

    public function academicSubgroup()
    {
        return $this->belongsTo(Subgroup::class);
    }

    public function week()
    {
        return $this->belongsTo(Week::class);
    }
}
