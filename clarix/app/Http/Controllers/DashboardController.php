<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        return match ($user->role) {
            // A superadmin has no agency, so an agency dashboard has nothing
            // to show them. Their home is the platform portal. Without this
            // arm the match would throw, which is what it did before the role
            // existed.
            'superadmin' => redirect()->route('superadmin.organizations.index'),
            'admin'      => view('dashboard.admin'),

            // A supervisor's figures are a PM's, counted across the agency
            // rather than one unit — PmStats widens itself for the role. The
            // page is its own view because the PM's carries a Credits button
            // the supervisor is not granted.
            'supervisor' => view('dashboard.supervisor'),

            'pm' => view('dashboard.pm'),

            // HR has no work to show. Their landing page is the three modules
            // they run, and nothing from Management leaks onto it.
            'hr' => view('dashboard.hr'),

            'writer' => view('dashboard.writer'),
        };
    }
}
