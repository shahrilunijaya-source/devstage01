<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * "How to Use" — a single static onboarding page that explains the URSB
 * workflow end to end (Portfolio → Sessions → Baseline → Verification → Change)
 * with a visual pipeline diagram. No model state; pure orientation content.
 */
class GuideController extends Controller
{
    public function index(): View
    {
        return view('guide.index');
    }
}
