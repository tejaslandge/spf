<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardService $dashboard): RedirectResponse
    {
        return redirect()->route(request()->user()->dashboardRoute());
    }

    public function admin(DashboardService $dashboard): View
    {
        return view('pages.dashboards.admin', [
            'metrics' => $dashboard->admin(),
        ]);
    }

    public function superAdmin(DashboardService $dashboard): View
    {
        return view('pages.dashboards.super-admin', [
            'metrics' => $dashboard->superAdmin(),
        ]);
    }
}
