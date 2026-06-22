<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalaryBand extends Model
{
    protected $fillable = ['position_id', 'max_salary', 'effective_from', 'effective_to'];

    protected $casts = [
        'max_salary' => 'decimal:2',
        'effective_from' => 'date',
        'effective_to' => 'date',
    ];

    public function position()
    {
        return $this->belongsTo(Position::class);
    }
}
