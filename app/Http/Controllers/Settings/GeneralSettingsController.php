<?php

namespace App\Http\Controllers\Settings;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateGeneralSettingsRequest;
use App\Models\User;
use App\Services\Settings\SettingsService;
use App\Support\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class GeneralSettingsController extends Controller
{
    public function __construct(private readonly SettingsService $settings) {}

    /**
     * Show the Super Admin general settings page.
     */
    public function edit(Request $request): View
    {
        abort_unless($request->user()?->hasAnyPermission([
            Permission::SettingsViewGeneral,
            Permission::SettingsModify,
        ]) ?? false, 403);

        $company = $this->settings->group('company');

        return view('pages.settings.general', [
            'company' => $company,
            'logoUrl' => filled($company['company.logo'] ?? null)
                ? Storage::disk('public')->url((string) $company['company.logo'])
                : null,
        ]);
    }

    /**
     * Persist the general settings. Authorization is enforced by the route
     * middleware and the Form Request.
     */
    public function update(UpdateGeneralSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        /** @var User $user */
        $user = $request->user();

        $values = [
            'company.name' => $validated['company_name'],
            'company.legal_name' => $validated['company_legal_name'] ?? '',
            'company.registration_number' => $validated['company_registration_number'] ?? '',
            'company.email' => $validated['company_email'] ?? '',
            'company.phone' => $validated['company_phone'] ?? '',
            'company.support_email' => $validated['company_support_email'] ?? '',
            'company.support_mobile' => $validated['company_support_mobile'] ?? '',
            'company.address' => $validated['company_address'] ?? '',
            'company.city' => $validated['company_city'] ?? '',
            'company.state' => $validated['company_state'] ?? '',
            'company.pincode' => $validated['company_pincode'] ?? '',
            'company.website' => $validated['company_website'] ?? '',
            'company.currency_code' => strtoupper($validated['company_currency_code'] ?? $this->settings->get('company.currency_code', 'INR')),
            'company.currency_symbol' => $validated['company_currency_symbol'] ?? $this->settings->get('company.currency_symbol', '₹'),
            'company.timezone' => $validated['company_timezone'] ?? $this->settings->get('company.timezone', 'Asia/Kolkata'),
            'company.date_format' => $validated['company_date_format'] ?? $this->settings->get('company.date_format', 'd M Y'),
            'company.time_format' => $validated['company_time_format'] ?? $this->settings->get('company.time_format', 'H:i'),
            'company.financial_year_start_month' => (int) ($validated['company_financial_year_start_month'] ?? $this->settings->getInt('company.financial_year_start_month', 4)),
        ];

        if ($request->hasFile('company_logo')) {
            $path = $request->file('company_logo')->store('branding', 'public');
            $this->deleteStoredLogo();

            $values['company.logo'] = $path;
        } elseif ($request->boolean('remove_logo')) {
            $this->deleteStoredLogo();

            $values['company.logo'] = null;
        }

        $this->settings->putMany($values, $user, $validated['reason'] ?? null);

        AuditLogger::log('settings.general_updated', $user, [], [
            'role' => $user->role->value,
            'group' => 'company',
            'keys' => array_keys($values),
        ]);

        return redirect()
            ->route('super-admin.settings.general')
            ->with('status', __('General settings saved.'));
    }

    private function deleteStoredLogo(): void
    {
        $existing = $this->settings->get('company.logo');

        if (is_string($existing) && $existing !== '' && Storage::disk('public')->exists($existing)) {
            Storage::disk('public')->delete($existing);
        }
    }
}
