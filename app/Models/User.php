<?php

namespace App\Models;

use App\Services\WorkloadService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'system_role', 'active'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = ['email_verified_at' => 'datetime', 'active' => 'boolean'];

    public function projectAssignments()
    {
        return $this->hasMany(ProjectAssignment::class);
    }

    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_assignments', 'user_id', 'project_id')->withPivot('project_role', 'assigned_at', 'removed_at')->wherePivotNull('removed_at');
    }

    public function auditLogs()
    {
        return $this->hasMany(AuditLog::class);
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function projectComments()
    {
        return $this->hasMany(ProjectComment::class);
    }

    public function isAdmin(): bool
    {
        return $this->system_role === 'admin';
    }

    public function isDirector(): bool
    {
        return $this->system_role === 'director';
    }

    public function isRegular(): bool
    {
        return $this->system_role === 'regular';
    }

    public function isClient(): bool
    {
        return $this->system_role === 'client';
    }

    /** Projects this user is attached to as a client (read-only portal). */
    public function clientProjects()
    {
        return $this->projects()->wherePivot('project_role', 'client');
    }

    public function projectRoleOn(Project $project): ?string
    {
        return $this->projectAssignments()
            ->where('project_id', $project->id)
            ->whereNull('removed_at')
            ->value('project_role');
    }

    public function isProjectPm(): bool
    {
        return $this->projectAssignments()
            ->whereNull('removed_at')
            ->where('project_role', 'pm')
            ->exists();
    }

    /**
     * Compact one-line workload summary for inline display
     * (e.g. the Manage Team modal candidate rows). Empty string when
     * the user carries no active PM/PE load.
     */
    public function getWorkloadSummaryAttribute(): string
    {
        $service = app(WorkloadService::class);
        $load = $service->personLoad($this);

        if (empty($load['rows'])) {
            return '';
        }

        return $service->formatSummary($load['total'], $load['band']);
    }
}
