<?php

namespace App\Models;

use App\Services\WorkloadService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'system_role', 'active', 'last_seen_version'];

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

    /**
     * The authoritative system role for every authorization decision: the new
     * `system_role` column, falling back to the legacy `role` column only when
     * `system_role` is unset. Reading all authz through this one accessor closes
     * the split where the PDP trusted `role` while helpers trusted `system_role`.
     */
    public function effectiveSystemRole(): ?string
    {
        return $this->system_role ?? $this->role;
    }

    public function isAdmin(): bool
    {
        return $this->effectiveSystemRole() === 'admin';
    }

    public function isDirector(): bool
    {
        return $this->effectiveSystemRole() === 'director';
    }

    public function isRegular(): bool
    {
        return $this->effectiveSystemRole() === 'regular';
    }

    public function isClient(): bool
    {
        return $this->effectiveSystemRole() === 'client';
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
