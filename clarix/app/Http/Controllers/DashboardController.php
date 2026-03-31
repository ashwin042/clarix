<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return match ($user->role) {
            'admin'  => view('dashboard.admin'),
            'pm'     => view('dashboard.pm'),
            'writer' => view('dashboard.writer'),
        };
    }
}
