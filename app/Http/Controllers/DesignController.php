<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ObjectType;
use App\Models\Graph\EngObject;
use App\Models\Project;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\Design\DesignService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Design register (PRD §12 — SDS/SLD/DBD). Read gated by 'view'; authoring
 * design objects is engineering work gated by 'edit'.
 */
class DesignController extends Controller
{
    public function __construct(
        private readonly PolicyDecisionPoint $pdp,
        private readonly DesignService $design,
    ) {}

    public function index(Request $request, Project $project): View
    {
        abort_unless($this->pdp->can($request->user(), 'view', $project)->permitted, 403, 'Access denied by ACL.');

        return view('design.index', $this->design->register($project) + [
            'canEdit' => $this->pdp->can($request->user(), 'edit', $project)->permitted,
            'designTypes' => array_keys(DesignService::DESIGN_TYPES),
        ]);
    }

    /** Author a design object that satisfies a requirement. */
    public function store(Request $request, EngObject $requirement): RedirectResponse
    {
        abort_unless($this->pdp->can($request->user(), 'edit', $requirement->project)->permitted, 403, 'Access denied by ACL.');
        $this->assertRequirement($requirement);

        $data = $request->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(DesignService::DESIGN_TYPES))],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:10000'],
        ]);

        $design = $this->design->addDesign($requirement, $data['type'], $data['title'], $data['body'] ?? null, $request->user());

        return redirect()->route('design.index', $requirement->project)
            ->with('status', "Design {$design->ref} added for {$requirement->ref}.");
    }

    private function assertRequirement(EngObject $object): void
    {
        abort_unless(in_array($object->type, [
            ObjectType::BUSINESS_REQUIREMENT, ObjectType::USER_REQUIREMENT,
            ObjectType::FUNCTIONAL_REQUIREMENT, ObjectType::NON_FUNCTIONAL_REQUIREMENT,
        ], true), 404);
    }
}
