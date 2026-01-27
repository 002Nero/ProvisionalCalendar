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
        'teaching_id',
        'promotion_id',
        'group_id',
        'subgroup_id',
        'room_amount',
        'is_neutralized',
        'is_exam',
        'week_id',
        'type_id',
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

    /**
     * Relation many-to-many avec les professeurs via la table pivot slots_teachers
     */
    public function teachers()
    {
        return $this->belongsToMany(Teacher::class, 'slots_teachers', 'slot_id', 'teacher_id')
            ->withTimestamps();
    }

    /**
     * @deprecated Use teachers() instead for many-to-many relationship
     */
    public function teacher()
    {
        return $this->belongsTo(Teacher::class);
    }

    /**
     * @deprecated Use teachers() instead for many-to-many relationship
     */
    public function substituteTeacher()
    {
        return $this->belongsTo(Teacher::class, 'substitute_teacher_id');
    }

    public function teaching()
    {
        return $this->belongsTo(Teaching::class);
    }

    public function promotion()
    {
        return $this->belongsTo(Promotion::class);
    }

    public function group()
    {
        return $this->belongsTo(Group::class);
    }

    public function subgroup()
    {
        return $this->belongsTo(Subgroup::class);
    }

    public function week()
    {
        return $this->belongsTo(Week::class);
    }
}
