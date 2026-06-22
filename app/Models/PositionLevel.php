<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PositionLevel extends Model
{
    protected $fillable = ['name', 'sort_order'];

    public function positions()
    {
        return $this->hasMany(Position::class, 'level_id');
    }
}
