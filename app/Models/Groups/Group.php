<?php

namespace App\Models\Groups;

use App\Models\Slot;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = [
        'name',
        'promotion_id',
        'student_amount',
    ];

    public function promotion()
    {
        return $this->belongsTo(Promotion::class, 'promotion_id');
    }

    public function subgroups()
    {
        return $this->hasMany(Subgroup::class);
    }

    protected $casts = [
        'student_amount' => 'integer',
    ];

    public function slots()
    {
        return $this->hasMany(Slot::class);
    }

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($group) {
            // Delete associated slots first
            $group->slots()->delete();
        });
    }
}
