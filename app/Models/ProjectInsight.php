<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProjectInsight extends Model
{
    protected $fillable = ['project_id', 'content', 'model', 'generated_at', 'generated_by'];

    protected $casts = [
        'content' => 'array',
        'generated_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
