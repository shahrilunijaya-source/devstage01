<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\InboxService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Cross-project action inbox — the items waiting on this user across every
 * project they can act on (PRD §9, §17).
 */
class InboxController extends Controller
{
    public function index(Request $request, InboxService $inbox): View
    {
        return view('inbox', [
            'inbox' => $inbox->forUser($request->user()),
        ]);
    }
}
