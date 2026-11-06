<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use App\Services\Settings\UserPreferenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(DashboardService $dashboard): RedirectResponse
    {
        $user = request()->user();
        $preferred = (string) app(UserPreferenceService::class)->get($user, 'dashboard', '');

        if ($preferred !== '' && Route::has($preferred)) {
            return redirect()->route($preferred);
        }

        return redirect()->route($user->dashboardRoute());
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
