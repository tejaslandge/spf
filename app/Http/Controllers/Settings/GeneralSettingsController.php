<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateGeneralSettingsRequest;
use App\Models\Setting;
use App\Models\User;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GeneralSettingsController extends Controller
{
    /**
     * Show the Super Admin general settings page.
     */
    public function edit(Request $request): View
    {
        abort_unless($request->user()?->isSuperAdmin(), 403);

        return view('pages.settings.general', [
            'companyName' => Setting::get('company.name', config('app.name')),
            'companyAddress' => Setting::get('company.address', ''),
            'companyPhone' => Setting::get('company.phone', ''),
        ]);
    }

    /**
     * Persist the general settings (Super Admin only, enforced by middleware and Form Request).
     */
    public function update(UpdateGeneralSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        Setting::put('company.name', $validated['company_name'], 'string', 'company');
        Setting::put('company.address', $validated['company_address'] ?? '', 'string', 'company');
        Setting::put('company.phone', $validated['company_phone'] ?? '', 'string', 'company');

        /** @var User $user */
        $user = $request->user();

        AuditLogger::log('settings.general_updated', $user, [], [
            'role' => $user->role->value,
            'group' => 'company',
            'keys' => ['company.name', 'company.address', 'company.phone'],
        ]);

        return redirect()
            ->route('super-admin.settings.general')
            ->with('status', __('General settings saved.'));
    }
}
