<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReportDelivery extends Model
{
    protected $fillable = ['monthly_report_id', 'delivery_method', 'status', 'recipients_to', 'recipients_cc', 'subject', 'body', 'attachment_hash', 'error', 'sent_at', 'sent_by', 'manual_delivery_note'];

    protected $casts = ['recipients_to' => 'array', 'recipients_cc' => 'array', 'sent_at' => 'datetime'];

    public function monthlyReport()
    {
        return $this->belongsTo(MonthlyReport::class);
    }

    public function sentBy()
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
