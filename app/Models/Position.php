<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    protected $fillable = ['department_id', 'level_id', 'name', 'code', 'is_delivery_role', 'active'];

    protected $casts = ['is_delivery_role' => 'boolean', 'active' => 'boolean'];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function level()
    {
        return $this->belongsTo(PositionLevel::class, 'level_id');
    }

    public function salaryBands()
    {
        return $this->hasMany(SalaryBand::class);
    }

    /**
     * Current salary band as of the given date (defaults to today).
     */
    public function currentSalaryBand(?string $asOf = null): ?SalaryBand
    {
        $asOf = $asOf ?? now()->toDateString();

        return $this->salaryBands()
            ->where('effective_from', '<=', $asOf)
            ->where(function ($q) use ($asOf) {
                $q->whereNull('effective_to')->orWhere('effective_to', '>=', $asOf);
            })
            ->orderByDesc('effective_from')
            ->first();
    }
}
