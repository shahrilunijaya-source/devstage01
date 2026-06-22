<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ObjectType;
use App\Models\Graph\EngObject;
use App\Models\Project;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\Prototype\PrototypeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Prototype register (PRD §12 — PROTOTYPE stage). Read gated by 'view';
 * authoring/advancing prototype elements is engineering work gated by 'edit'.
 */
class PrototypeController extends Controller
{
    public function __construct(
        private readonly PolicyDecisionPoint $pdp,
        private readonly PrototypeService $prototype,
    ) {}

    public function index(Request $request, Project $project): View
    {
        abort_unless($this->pdp->can($request->user(), 'view', $project)->permitted, 403, 'Access denied by ACL.');

        return view('prototype.index', $this->prototype->register($project) + [
            'canEdit' => $this->pdp->can($request->user(), 'edit', $project)->permitted,
            'states' => PrototypeService::STATES,
        ]);
    }

    /** Add a prototype element implementing a requirement. */
    public function store(Request $request, EngObject $requirement): RedirectResponse
    {
        abort_unless($this->pdp->can($request->user(), 'edit', $requirement->project)->permitted, 403, 'Access denied by ACL.');
        $this->assertRequirement($requirement);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:10000'],
        ]);

        $element = $this->prototype->addElement($requirement, $data['title'], $data['body'] ?? null, $request->user());

        return redirect()->route('prototype.index', $requirement->project)
            ->with('status', "Prototype element {$element->ref} added for {$requirement->ref}.");
    }

    /** Advance a prototype element's build state. */
    public function setState(Request $request, EngObject $element): RedirectResponse
    {
        abort_unless($this->pdp->can($request->user(), 'edit', $element->project)->permitted, 403, 'Access denied by ACL.');
        abort_unless($element->type === ObjectType::PROTOTYPE_ELEMENT, 404);

        $data = $request->validate(['state' => ['required', 'in:'.implode(',', PrototypeService::STATES)]]);

        $this->prototype->setState($element, $data['state'], $request->user());

        return redirect()->route('prototype.index', $element->project)
            ->with('status', "Prototype element {$element->ref} → {$data['state']}.");
    }

    private function assertRequirement(EngObject $object): void
    {
        abort_unless(in_array($object->type, [
            ObjectType::BUSINESS_REQUIREMENT, ObjectType::USER_REQUIREMENT,
            ObjectType::FUNCTIONAL_REQUIREMENT, ObjectType::NON_FUNCTIONAL_REQUIREMENT,
        ], true), 404);
    }
}
