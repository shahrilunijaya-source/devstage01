<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MonthlyReport extends Model
{
    protected $fillable = ['project_id', 'reporting_month', 'snapshot_data', 'executive_summary', 'pdf_path', 'status', 'generated_by', 'finalised_at', 'approved_at', 'approved_by'];

    protected $casts = ['reporting_month' => 'date', 'snapshot_data' => 'array', 'finalised_at' => 'datetime', 'approved_at' => 'datetime'];

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function generatedBy()
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function deliveries()
    {
        return $this->hasMany(ReportDelivery::class);
    }

    // Approved = finalised content that the PM has explicitly authorised for sending.
    public function getIsApprovedAttribute(): bool
    {
        return ! is_null($this->approved_at);
    }
}
