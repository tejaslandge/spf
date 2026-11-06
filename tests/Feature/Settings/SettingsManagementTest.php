<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function settingsSuperAdmin(): User
{
    return User::factory()->superAdmin()->create();
}

function settingsAdmin(): User
{
    return User::factory()->create(['role' => UserRole::Admin]);
}

test('super admin can access the super admin settings page', function () {
    $this->actingAs(settingsSuperAdmin())
        ->get(route('super-admin.settings'))
        ->assertOk()
        ->assertSee('Super Admin Settings')
        ->assertSee('System Settings')
        ->assertSee('General Settings');
});

test('admin can access the admin settings page', function () {
    $this->actingAs(settingsAdmin())
        ->get(route('admin.settings'))
        ->assertOk()
        ->assertSee('Admin Settings')
        ->assertDontSee('General Settings');
});

test('admin cannot access super admin settings', function () {
    $this->actingAs(settingsAdmin())
        ->get(route('super-admin.settings'))
        ->assertForbidden();
});

test('super admin can access admin-facing routes', function () {
    $this->actingAs(settingsSuperAdmin())
        ->get(route('profile.edit'))
        ->assertOk();
});

test('admin cannot access the super admin general settings page', function () {
    $this->actingAs(settingsAdmin())
        ->get(route('super-admin.settings.general'))
        ->assertForbidden();
});

test('admin cannot update super admin general settings', function () {
    $admin = settingsAdmin();

    Setting::put('company.name', 'Original Name', 'string', 'company');

    $this->actingAs($admin)
        ->put(route('super-admin.settings.general.update'), [
            'company_name' => 'Hacked Name',
            'company_address' => 'Somewhere',
            'company_phone' => '+91 9000000000',
        ])
        ->assertForbidden();

    expect(Setting::get('company.name'))->toBe('Original Name');
});

test('super admin can view and update general settings', function () {
    $superAdmin = settingsSuperAdmin();

    $this->actingAs($superAdmin)
        ->get(route('super-admin.settings.general'))
        ->assertOk()
        ->assertSee('Company name');

    $this->actingAs($superAdmin)
        ->put(route('super-admin.settings.general.update'), [
            'company_name' => 'Acme Capital',
            'company_address' => 'Fort, Mumbai',
            'company_phone' => '+91 90000 12345',
        ])
        ->assertRedirect(route('super-admin.settings.general'));

    expect(Setting::get('company.name'))->toBe('Acme Capital')
        ->and(Setting::get('company.address'))->toBe('Fort, Mumbai')
        ->and(Setting::get('company.phone'))->toBe('+91 90000 12345');

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'settings.general_updated',
        'user_id' => $superAdmin->id,
    ]);
});

test('general settings rejects invalid phone input', function () {
    $this->actingAs(settingsSuperAdmin())
        ->put(route('super-admin.settings.general.update'), [
            'company_name' => 'Acme Capital',
            'company_phone' => 'not-a-phone-number!!',
        ])
        ->assertSessionHasErrors('company_phone');
});

test('appearance settings route no longer exists', function () {
    $this->actingAs(settingsAdmin())
        ->get('/settings/appearance')
        ->assertNotFound();
});

test('settings pages do not offer appearance or delete account options', function () {
    $this->actingAs(settingsAdmin())
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertDontSee('settings/appearance')
        ->assertDontSee('Delete account')
        ->assertDontSee('Delete My Account');

    $this->actingAs(settingsAdmin())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertDontSee('settings/appearance');
});

test('security page renders the csrf token for the password form', function () {
    $this->actingAs(settingsAdmin())
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertSee('name="_token"', escape: false)
        ->assertSee('name="_method"', escape: false);
});

test('guests are redirected to login for settings and password routes', function () {
    $this->get(route('admin.settings'))->assertRedirect(route('login'));
    $this->get(route('super-admin.settings'))->assertRedirect(route('login'));
    $this->get(route('super-admin.settings.general'))->assertRedirect(route('login'));
    $this->get(route('profile.edit'))->assertRedirect(route('login'));
    $this->put(route('security.password.update'), [])->assertRedirect(route('login'));
});

test('password can be updated with the correct current password', function () {
    $user = User::factory()->create(['password' => Hash::make('Current-Password-123')]);

    $this->actingAs($user)
        ->put(route('security.password.update'), [
            'current_password' => 'Current-Password-123',
            'password' => 'New-Password-456!',
            'password_confirmation' => 'New-Password-456!',
        ])
        ->assertRedirect(route('security.edit'));

    $user->refresh();

    expect(Hash::check('New-Password-456!', $user->password))->toBeTrue()
        ->and(Hash::check('Current-Password-123', $user->password))->toBeFalse()
        ->and($user->password_changed_at)->not->toBeNull();

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'password.changed',
        'user_id' => $user->id,
    ]);

    $log = AuditLog::query()->where('action', 'password.changed')->latest()->first();

    expect(json_encode($log->new_values))->not->toContain('New-Password-456!');
});

test('incorrect current password is rejected', function () {
    $user = User::factory()->create(['password' => Hash::make('Current-Password-123')]);

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('security.password.update'), [
            'current_password' => 'Wrong-Password-999',
            'password' => 'New-Password-456!',
            'password_confirmation' => 'New-Password-456!',
        ])
        ->assertRedirect(route('security.edit'))
        ->assertSessionHasErrors('current_password');

    expect(Hash::check('Current-Password-123', $user->refresh()->password))->toBeTrue();
});

test('password confirmation mismatch is rejected', function () {
    $user = User::factory()->create(['password' => Hash::make('Current-Password-123')]);

    $this->actingAs($user)
        ->put(route('security.password.update'), [
            'current_password' => 'Current-Password-123',
            'password' => 'New-Password-456!',
            'password_confirmation' => 'Different-Password-789!',
        ])
        ->assertSessionHasErrors('password');

    expect(Hash::check('Current-Password-123', $user->refresh()->password))->toBeTrue();
});

test('new password must differ from the current password', function () {
    $user = User::factory()->create(['password' => Hash::make('Current-Password-123')]);

    $this->actingAs($user)
        ->put(route('security.password.update'), [
            'current_password' => 'Current-Password-123',
            'password' => 'Current-Password-123',
            'password_confirmation' => 'Current-Password-123',
        ])
        ->assertSessionHasErrors('password');
});

test('weak passwords are rejected', function () {
    $user = User::factory()->create(['password' => Hash::make('Current-Password-123')]);

    $this->actingAs($user)
        ->put(route('security.password.update'), [
            'current_password' => 'Current-Password-123',
            'password' => 'short',
            'password_confirmation' => 'short',
        ])
        ->assertSessionHasErrors('password');

    expect(Hash::check('Current-Password-123', $user->refresh()->password))->toBeTrue();
});

test('login works with the new password and fails with the old password', function () {
    config([
        'services.renflair.url' => 'https://sms.renflair.in/V1.php',
        'services.renflair.api_key' => 'test-api-key',
    ]);

    Http::fake([
        '*sms.renflair.in*' => Http::response(['success' => true]),
    ]);

    $user = User::factory()->create([
        'email' => 'operator@finance.test',
        'password' => Hash::make('Current-Password-123'),
    ]);

    $this->actingAs($user)
        ->put(route('security.password.update'), [
            'current_password' => 'Current-Password-123',
            'password' => 'New-Password-456!',
            'password_confirmation' => 'New-Password-456!',
        ])
        ->assertRedirect(route('security.edit'));

    auth()->logout();

    $this->post(route('login.store'), [
        'email' => 'operator@finance.test',
        'password' => 'New-Password-456!',
    ])->assertRedirect(route('login.otp'));

    $this->assertGuest();

    $this->post(route('login.otp.verify'), [
        'code' => lastRenflairOtp(),
    ])->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($user);

    auth()->logout();

    $this->post(route('login.store'), [
        'email' => 'operator@finance.test',
        'password' => 'Current-Password-123',
    ])->assertSessionHasErrors();

    $this->assertGuest();
});

test('password update endpoint is rate limited', function () {
    $user = User::factory()->create(['password' => Hash::make('Current-Password-123')]);

    $this->actingAs($user);

    for ($attempt = 1; $attempt <= 5; $attempt++) {
        $this->put(route('security.password.update'), [
            'current_password' => 'Wrong-Password-999',
            'password' => 'New-Password-456!',
            'password_confirmation' => 'New-Password-456!',
        ])->assertSessionHasErrors('current_password');
    }

    $this->put(route('security.password.update'), [
        'current_password' => 'Wrong-Password-999',
        'password' => 'New-Password-456!',
        'password_confirmation' => 'New-Password-456!',
    ])->assertTooManyRequests();
});

test('profile updates are audit logged and never change the role', function () {
    $user = settingsAdmin();

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', 'Updated Operator')
        ->set('email', 'updated-operator@finance.test')
        ->set('mobile', '9876543210')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    $user->refresh();

    expect($user->name)->toBe('Updated Operator')
        ->and($user->mobile)->toBe('9876543210')
        ->and($user->role)->toBe(UserRole::Admin);

    $this->assertDatabaseHas('audit_logs', [
        'action' => 'profile.updated',
        'user_id' => $user->id,
    ]);
});

test('profile update rejects duplicate mobile numbers', function () {
    $existing = settingsAdmin();
    $user = settingsAdmin();

    $this->actingAs($user);

    Livewire::test('pages::settings.profile')
        ->set('name', $user->name)
        ->set('email', $user->email)
        ->set('mobile', $existing->mobile)
        ->call('updateProfileInformation')
        ->assertHasErrors(['mobile']);
});

test('admin settings page shows account section but no system settings', function () {
    $this->actingAs(settingsAdmin())
        ->get(route('admin.settings'))
        ->assertOk()
        ->assertSee('Account')
        ->assertSee('My Profile')
        ->assertSee('Security')
        ->assertDontSee('System Settings');
});
