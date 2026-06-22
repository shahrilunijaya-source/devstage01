<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WorkloadService;

class WorkloadController extends Controller
{
    public function index(WorkloadService $workload)
    {
        $actor = auth()->user();
        abort_unless($actor->isAdmin() || $actor->isDirector(), 403);

        return view('workload.index', [
            'heatmap' => $workload->heatmap(),
        ]);
    }

    public function show(User $user, WorkloadService $workload)
    {
        $actor = auth()->user();
        abort_unless(
            $actor->isAdmin() || $actor->isDirector() || $actor->id === $user->id,
            403
        );
        abort_if(! $user->active, 404);

        return view('workload.show', [
            'load' => $workload->personLoad($user),
        ]);
    }
}
