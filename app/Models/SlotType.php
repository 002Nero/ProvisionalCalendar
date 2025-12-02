<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SlotType extends Model
{
    protected $fillable = [
        'name',
        'acronym',
        'slot_order',
        'color',
    ];
}

