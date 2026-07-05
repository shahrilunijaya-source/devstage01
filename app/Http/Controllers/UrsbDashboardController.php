<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Acl\Permission;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Graph\TraceRelationship;
use App\Models\Knowledge\KnowledgeBook;
use App\Models\Portfolio\Tenant;
use App\Services\Graph\TraceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Read-only Phase-1 verification dashboard. Renders the live canonical model
 * across every tenant, so it is admin-only — a scoped user must never see the
 * whole portfolio through this inspector.
 */
class UrsbDashboardController extends Controller
{
    public function index(Request $request, TraceService $trace): View
    {
        abort_unless($request->user()->isAdmin(), 403, 'Administrators only.');

        $tenants = Tenant::with(['projects.modules.stages'])->get();

        $firstEvidence = EngObject::where('type', 'evidence')->orderBy('id')->first();
        $chain = $firstEvidence
            ? collect([$firstEvidence, ...$trace->forwardTrace($firstEvidence)])
            : collect();

        return view('ursb.dashboard', [
            'tenants' => $tenants,
            'chain' => $chain,
            'counts' => [
                'tenants' => Tenant::count(),
                'objects' => EngObject::count(),
                'traces' => TraceRelationship::count(),
                'roles' => Role::count(),
                'permissions' => Permission::count(),
                'bindings' => ScopeBinding::count(),
            ],
            'books' => KnowledgeBook::withCount('items')->get(),
        ]);
    }
}
