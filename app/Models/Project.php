<?php

namespace App\Models;

use App\Models\Portfolio\Module;
use App\Models\Portfolio\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'code',
        'status',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(ProjectAssignment::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class);
    }
}
