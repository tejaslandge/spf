<?php

use App\Enums\AdminDesignation;
use App\Enums\Permission;
use App\Enums\UserRole;
use App\Models\Borrower;
use App\Models\InterestConfiguration;
use App\Models\KycDocument;
use App\Models\Setting;
use App\Models\SettingAuditLog;
use App\Models\SettingVersion;
use App\Models\User;
use App\Services\Settings\SettingsService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

function moduleSuperAdmin(): User
{
    return User::factory()->superAdmin()->create();
}

/**
 * @param  array<int, Permission>  $permissions
 */
function moduleAdmin(array $permissions = []): User
{
    return User::factory()->create([
        'role' => UserRole::Admin,
        'permissions' => array_map(fn (Permission $permission): string => $permission->value, $permissions),
    ]);
}

test('super admin can open every settings page', function () {
    $this->actingAs(moduleSuperAdmin());

    foreach ([
        'super-admin.settings',
        'super-admin.settings.general',
        'super-admin.settings.loans',
        'super-admin.settings.interest',
        'super-admin.settings.investors',
        'super-admin.settings.kyc',
        'super-admin.settings.security',
        'super-admin.users.index',
        'settings.notifications',
        'settings.preferences',
    ] as $route) {
        $this->get(route($route))->assertOk();
    }
});

test('admin cannot open super admin only settings pages', function () {
    $this->actingAs(moduleAdmin());

    $this->get(route('super-admin.settings.investors'))->assertForbidden();
    $this->get(route('super-admin.settings.kyc'))->assertForbidden();
    $this->get(route('super-admin.settings.security'))->assertForbidden();
    $this->get(route('super-admin.users.index'))->assertForbidden();
});

test('admin needs the loan settings permission to view and modify', function () {
    $loanUrl = route('super-admin.settings.loans');

    $this->actingAs(moduleAdmin())->get($loanUrl)->assertForbidden();

    $viewer = moduleAdmin([Permission::SettingsViewLoan]);
    $this->actingAs($viewer)->get($loanUrl)->assertOk();

    Livewire::actingAs($viewer)
        ->test('pages::settings.loans')
        ->set('reason', 'Attempt without modify permission')
        ->call('save')
        ->assertStatus(403);

    $editor = moduleAdmin([Permission::SettingsModify]);
    $this->actingAs($editor)->get($loanUrl)->assertOk();
});

test('loan settings are saved with an audit trail and a version snapshot', function () {
    $this->actingAs(moduleSuperAdmin());

    Livewire::test('pages::settings.loans')
        ->set('minAmount', '5000')
        ->set('maxAmount', '250000')
        ->set('maxTenure', '240')
        ->set('tenureOptions', '12, 24, 36')
        ->set('maxMonthlyRate', '4')
        ->set('penaltyEnabled', true)
        ->set('penaltyRate', '0.5')
        ->set('penaltyGraceDays', '5')
        ->set('reason', 'Board approved revised loan policy')
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(SettingsService::class);

    expect($settings->getDecimal('loan.min_amount'))->toBe('5000')
        ->and($settings->getDecimal('loan.max_amount'))->toBe('250000')
        ->and($settings->getInt('loan.max_tenure_months'))->toBe(240)
        ->and($settings->getJson('loan.tenure_options'))->toBe([12, 24, 36])
        ->and($settings->getBool('loan.penalty_enabled'))->toBeTrue()
        ->and(SettingVersion::query()->count())->toBe(1)
        ->and(SettingAuditLog::query()->where('setting_key', 'loan.max_amount')->value('reason'))->toBe('Board approved revised loan policy');

    $audit = SettingAuditLog::query()->where('setting_key', 'loan.max_amount')->first();

    expect($audit->old_value)->toBe('100000000')
        ->and($audit->new_value)->toBe('250000');
});

test('loan settings reject a maximum amount below the minimum', function () {
    $this->actingAs(moduleSuperAdmin());

    Livewire::test('pages::settings.loans')
        ->set('minAmount', '900000')
        ->set('maxAmount', '100000')
        ->set('reason', 'Invalid configuration test')
        ->call('save')
        ->assertHasErrors('maxAmount');
});

test('loan settings reject enabling a penalty without a rate', function () {
    $this->actingAs(moduleSuperAdmin());

    Livewire::test('pages::settings.loans')
        ->set('penaltyEnabled', true)
        ->set('penaltyRate', '0')
        ->set('reason', 'Penalty without rate')
        ->call('save')
        ->assertHasErrors('penaltyRate');
});

test('interest distribution settings create a derived configuration version', function () {
    $this->actingAs(moduleSuperAdmin());

    Livewire::test('pages::settings.interest')
        ->set('configName', 'Six percent policy')
        ->set('borrowerMonthlyRate', '6')
        ->set('investorMonthlyRate', '2')
        ->set('adminCommissionRate', '1')
        ->set('reason', 'Approved rate distribution for new disbursements')
        ->call('save')
        ->assertHasNoErrors();

    $configuration = InterestConfiguration::query()->latest('id')->first();

    expect($configuration)->not->toBeNull()
        ->and((string) $configuration->borrower_monthly_rate)->toBe('6.000000')
        ->and((string) $configuration->default_investor_monthly_rate)->toBe('2.000000')
        ->and((string) $configuration->default_admin_commission_rate)->toBe('1.0000')
        ->and((string) $configuration->investor_allocation_percent)->toBe('33.3333')
        ->and((string) $configuration->admin_allocation_percent)->toBe('16.6667')
        ->and((string) $configuration->company_allocation_percent)->toBe('50.0000');

    $this->assertDatabaseHas('audit_logs', ['action' => 'interest.configuration.created']);
});

test('interest distribution settings reject a negative company margin', function () {
    $this->actingAs(moduleSuperAdmin());

    $before = InterestConfiguration::query()->count();

    Livewire::test('pages::settings.interest')
        ->set('borrowerMonthlyRate', '6')
        ->set('investorMonthlyRate', '4')
        ->set('adminCommissionRate', '3')
        ->set('reason', 'Invalid margin test')
        ->call('save')
        ->assertHasErrors('adminCommissionRate');

    expect(InterestConfiguration::query()->count())->toBe($before);
});

test('investor and deposit settings persist with audit records', function () {
    $this->actingAs(moduleSuperAdmin());

    Livewire::test('pages::settings.investors')
        ->set('minInvestment', '50000')
        ->set('maxInvestment', '5000000')
        ->set('requireKycVerified', true)
        ->set('depositMin', '1000')
        ->set('depositMax', '2500000')
        ->set('verificationRequired', true)
        ->set('minAllocationAmount', '10000')
        ->set('reason', 'Tightened investor eligibility rules')
        ->call('save')
        ->assertHasNoErrors();

    $settings = app(SettingsService::class);

    expect($settings->getDecimal('investor.min_investment'))->toBe('50000')
        ->and($settings->getDecimal('investor.max_investment'))->toBe('5000000')
        ->and($settings->getBool('investor.require_kyc_verified'))->toBeTrue()
        ->and($settings->getDecimal('deposit.min_amount'))->toBe('1000')
        ->and($settings->getDecimal('deposit.max_amount'))->toBe('2500000')
        ->and($settings->getDecimal('deposit.min_allocation_amount'))->toBe('10000')
        ->and(SettingAuditLog::query()->where('setting_key', 'deposit.min_amount')->exists())->toBeTrue();
});

test('kyc settings drive the mandatory document types', function () {
    $this->actingAs(moduleSuperAdmin());

    Livewire::test('pages::settings.kyc')
        ->set('borrowerDocuments.income_proof.enabled', true)
        ->set('borrowerDocuments.income_proof.mandatory', true)
        ->set('borrowerDocuments.electricity_bill.mandatory', false)
        ->set('maxFileSizeKb', '10240')
        ->set('reason', 'Added income proof for high-value loans')
        ->call('save')
        ->assertHasNoErrors();

    $mandatory = KycDocument::mandatoryTypesFor(Borrower::class);

    expect($mandatory)->toContain('income_proof')
        ->and($mandatory)->not->toContain('electricity_bill')
        ->and(app(SettingsService::class)->getInt('kyc.max_file_size_kb'))->toBe(10240);
});

test('kyc settings reject a mandatory document that is disabled', function () {
    $this->actingAs(moduleSuperAdmin());

    Livewire::test('pages::settings.kyc')
        ->set('borrowerDocuments.bank_statement.enabled', false)
        ->set('borrowerDocuments.bank_statement.mandatory', true)
        ->call('save')
        ->assertHasErrors('borrowerDocuments.bank_statement.enabled');
});

test('general settings persist regional configuration and the company logo', function () {
    Storage::fake('public');

    $this->actingAs(moduleSuperAdmin());

    $this->put(route('super-admin.settings.general.update'), [
        'company_name' => 'Acme Capital',
        'company_legal_name' => 'Acme Capital Private Limited',
        'company_registration_number' => 'U65990MH2026PTC123456',
        'company_email' => 'hello@acme.test',
        'company_support_email' => 'support@acme.test',
        'company_support_mobile' => '+91 90000 11111',
        'company_address' => 'Fort, Mumbai',
        'company_city' => 'Mumbai',
        'company_state' => 'Maharashtra',
        'company_pincode' => '400001',
        'company_website' => 'https://acme.test',
        'company_logo' => UploadedFile::fake()->image('logo.png'),
        'company_currency_code' => 'INR',
        'company_currency_symbol' => '₹',
        'company_timezone' => 'Asia/Kolkata',
        'company_date_format' => 'd M Y',
        'company_time_format' => 'H:i',
        'company_financial_year_start_month' => '4',
        'reason' => 'Company profile update',
    ])->assertRedirect(route('super-admin.settings.general'));

    $settings = app(SettingsService::class);

    expect($settings->get('company.legal_name'))->toBe('Acme Capital Private Limited')
        ->and($settings->get('company.pincode'))->toBe('400001')
        ->and($settings->getInt('company.financial_year_start_month'))->toBe(4)
        ->and($settings->get('company.logo'))->not->toBeNull();

    Storage::disk('public')->assertExists($settings->get('company.logo'));
});

test('general settings reject an invalid timezone', function () {
    $this->actingAs(moduleSuperAdmin());

    $this->put(route('super-admin.settings.general.update'), [
        'company_name' => 'Acme Capital',
        'company_timezone' => 'Mars/Olympus',
    ])->assertSessionHasErrors('company_timezone');
});

test('admin permissions are assigned and revoked through the users page', function () {
    $this->actingAs(moduleSuperAdmin());

    $admin = moduleAdmin();

    Livewire::test('pages::users.index')
        ->call('openPermissionsModal', $admin->id)
        ->set('selectedPermissions', [
            Permission::LoansView->value,
            Permission::LoansApprove->value,
        ])
        ->call('savePermissions')
        ->assertHasNoErrors();

    expect($admin->refresh()->permissions)->toBe(['loans.view', 'loans.approve'])
        ->and($admin->hasPermission(Permission::LoansApprove))->toBeTrue();

    $this->assertDatabaseHas('audit_logs', ['action' => 'admin.permissions_updated', 'user_id' => auth()->id()]);

    Livewire::test('pages::users.index')
        ->call('openPermissionsModal', $admin->id)
        ->set('selectedPermissions', [])
        ->call('savePermissions');

    expect($admin->refresh()->permissions)->toBe([])
        ->and($admin->hasPermission(Permission::LoansApprove))->toBeFalse();
});

test('a designation template never grants access until it is saved', function () {
    $this->actingAs(moduleSuperAdmin());

    $admin = moduleAdmin();

    Livewire::test('pages::users.index')
        ->call('openEditModal', $admin->id)
        ->set('designation', AdminDesignation::FinanceManager->value)
        ->call('update')
        ->assertHasNoErrors();

    expect($admin->refresh()->designation)->toBe(AdminDesignation::FinanceManager)
        ->and($admin->permissions)->toBe([]);

    Livewire::test('pages::users.index')
        ->call('openPermissionsModal', $admin->id)
        ->call('applyDesignationTemplate')
        ->call('savePermissions')
        ->assertHasNoErrors();

    expect($admin->refresh()->permissions)->toContain(Permission::FinancialReports->value);
});

test('a legacy configuration is presented as an equivalent rate distribution', function () {
    $legacy = InterestConfiguration::query()->create([
        'name' => 'Legacy split',
        'effective_from' => now()->toDateString(),
        'borrower_monthly_rate' => '1.5',
        'default_investor_monthly_rate' => '1.5',
        'investor_allocation_percent' => '60',
        'admin_allocation_percent' => '10',
        'company_allocation_percent' => '30',
        'default_admin_commission_rate' => '1',
        'is_active' => true,
    ]);

    $rates = $legacy->rateBreakdown();

    expect(round((float) $rates['investor'], 4))->toBe(0.9)
        ->and(round((float) $rates['admin'], 4))->toBe(0.15)
        ->and(round((float) $rates['company'], 4))->toBe(0.45);

    $explicit = InterestConfiguration::query()->create([
        'name' => 'Explicit rates',
        'effective_from' => now()->toDateString(),
        'borrower_monthly_rate' => '6',
        'default_investor_monthly_rate' => '2',
        'investor_allocation_percent' => '33.3333',
        'admin_allocation_percent' => '16.6667',
        'company_allocation_percent' => '50',
        'default_admin_commission_rate' => '1',
        'is_active' => true,
    ]);

    expect(round((float) $explicit->rateBreakdown()['company'], 4))->toBe(3.0);
});

test('settings cache is invalidated after a save', function () {
    $this->actingAs(moduleSuperAdmin());

    Setting::put('company.name', 'Original', 'string', 'company');

    expect(Setting::get('company.name'))->toBe('Original');

    app(SettingsService::class)->put('company.name', 'Updated', auth()->user(), 'Rename');

    expect(Setting::get('company.name'))->toBe('Updated');
});
