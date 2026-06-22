<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectAssignment extends Model
{
    protected $fillable = ['project_id', 'user_id', 'project_role', 'assigned_by', 'assigned_at', 'removed_at'];

    protected $casts = ['assigned_at' => 'datetime', 'removed_at' => 'datetime'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('removed_at');
    }
}
