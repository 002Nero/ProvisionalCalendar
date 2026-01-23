<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Validation\ValidationException;
use App\Models\Groups\Promotion;

class Year extends Model
{
    protected $fillable = [
        'name',
        'periodicity'
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($year) {
            if (!in_array($year->periodicity, ['Semestrial', 'Trimestrial'])) {
                throw ValidationException::withMessages(['periodicity' => 'Invalid periodicity']);
            }
        });
    }

    public function weeks()
    {
        return $this->hasMany(Week::class);
    }

    public function teachers()
    {
        return $this->hasMany(Teacher::class);
    }

    public function teachings()
    {
        return $this->hasMany(Teaching::class);
    }

    public function Promotions()
    {
        return $this->hasMany(Promotion::class);
    }

    public function alerts()
    {
        return $this->hasMany(Alert::class);
    }

    public function semesters()
    {
        return $this->hasMany(Semester::class);
    }

    public function trimesters()
    {
        return $this->hasMany(Trimester::class);
    }
}
