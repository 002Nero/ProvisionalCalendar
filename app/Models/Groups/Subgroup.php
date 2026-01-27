<?php

namespace App\Models\Groups;

use Illuminate\Database\Eloquent\Model;

class Subgroup extends Model
{
    protected $fillable = [
        'name',
        'group_id',
    ];

    protected $with = ['Group.Promotion'];

    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }
}
