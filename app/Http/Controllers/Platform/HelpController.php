<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * GET /help: the in-app user guide, one guide per default role (content in resources/js/pages/Help/guides.ts).
 * Every signed-in user may read it; their own role codes only decide which guide opens first.
 */
class HelpController extends Controller
{
    public function __invoke(Request $request): Response
    {
        return Inertia::render('Help/Index', [
            'myRoles' => $request->user()->getRoleNames()->values()->all(),
        ]);
    }
}
