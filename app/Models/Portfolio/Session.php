<?php

declare(strict_types=1);

namespace App\Models\Portfolio;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A URSB requirement session (PRD §5, §9). Session dimension = Module /
 * Champion / Process / Domain / Location. Table is `requirement_sessions`
 * (avoids the Laravel auth `sessions` table).
 */
class Session extends Model
{
    protected $table = 'requirement_sessions';

    protected $fillable = [
        'stage_id', 'module_id', 'project_id', 'title',
        'champion_user_id', 'process', 'domain', 'location',
        'status', 'phase', 'firewall_approved_by', 'firewall_approved_at',
        'firewall_rejected_by', 'firewall_rejected_at', 'firewall_rejected_reason',
        'approved_by', 'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'firewall_approved_at' => 'datetime',
        'firewall_rejected_at' => 'datetime',
    ];

    public function stage(): BelongsTo
    {
        return $this->belongsTo(Stage::class);
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function champion(): BelongsTo
    {
        return $this->belongsTo(User::class, 'champion_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
