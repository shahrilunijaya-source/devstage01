<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SentimentScore extends Model
{
    protected $fillable = [
        'project_id', 'source_type', 'source_id',
        'label', 'score', 'summary', 'model', 'source_date', 'scored_at',
    ];

    protected $casts = [
        'score' => 'integer',
        'source_date' => 'date',
        'scored_at' => 'datetime',
    ];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
