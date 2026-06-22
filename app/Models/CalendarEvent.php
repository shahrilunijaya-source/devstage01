<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CalendarEvent extends Model
{
    protected $fillable = ['project_id', 'event_type', 'title', 'start_at', 'end_at', 'notes', 'created_by'];

    protected $casts = ['start_at' => 'datetime', 'end_at' => 'datetime'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
