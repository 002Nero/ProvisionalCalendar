<?php

namespace App\Models\Groups;

use App\Models\Slot;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
    protected $fillable = [
        'name',
        'promotion_id',
    ];

    public function Promotion()
    {
        return $this->belongsTo(Promotion::class, "promotion_id");
    }

    public function academicPromotion()
    {
        return $this->Promotion();
    }

    public function Subgroups()
    {
        return $this->hasMany(Subgroup::class);
    }

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
