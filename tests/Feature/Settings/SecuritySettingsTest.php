<?php

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Security\SessionService;
use App\Services\Settings\SettingsService;
use App\Services\System\DatabaseBackupService;
use App\Support\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

function policySuperAdmin(): User
{
    return User::factory()->superAdmin()->create();
}

test('the configured password policy is enforced on password change', function () {
    $superAdmin = policySuperAdmin();

    app(SettingsService::class)->putMany([
        'security.password_min_length' => 16,
        'security.password_require_mixed_case' => true,
        'security.password_require_numbers' => true,
        'security.password_require_symbols' => true,
    ], $superAdmin, 'Security hardening');

    $user = User::factory()->create(['password' => Hash::make('Current-Password-123')]);

    $this->actingAs($user)
        ->put(route('security.password.update'), [
            'current_password' => 'Current-Password-123',
            'password' => 'Short-Pass-1!',
            'password_confirmation' => 'Short-Pass-1!',
        ])
        ->assertSessionHasErrors('password');

    expect(Hash::check('Current-Password-123', $user->refresh()->password))->toBeTrue();
});

test('password reuse is blocked when a reuse restriction is configured', function () {
    $superAdmin = policySuperAdmin();

    app(SettingsService::class)->putMany([
        'security.password_history_count' => 3,
    ], $superAdmin, 'Reuse restriction');

    $user = User::factory()->create(['password' => Hash::make('Old-Password-123!')]);

    $this->actingAs($user)
        ->put(route('security.password.update'), [
            'current_password' => 'Old-Password-123!',
            'password' => 'New-Password-456!',
            'password_confirmation' => 'New-Password-456!',
        ])
        ->assertRedirect(route('security.edit'));

    $this->actingAs($user->refresh())
        ->put(route('security.password.update'), [
            'current_password' => 'New-Password-456!',
            'password' => 'Old-Password-123!',
            'password_confirmation' => 'Old-Password-123!',
        ])
        ->assertSessionHasErrors('password');

    expect(Hash::check('New-Password-456!', $user->refresh()->password))->toBeTrue();
});

test('login is locked after the configured number of failed attempts', function () {
    $superAdmin = policySuperAdmin();

    app(SettingsService::class)->putMany([
        'security.max_login_attempts' => 3,
        'security.login_lockout_minutes' => 15,
    ], $superAdmin, 'Lockout policy');

    User::factory()->create([
        'email' => 'locked.operator@finance.test',
        'password' => Hash::make('Correct-Password-1!'),
    ]);

    for ($attempt = 1; $attempt <= 3; $attempt++) {
        $this->post(route('login.store'), [
            'email' => 'locked.operator@finance.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors();
    }

    $this->post(route('login.store'), [
        'email' => 'locked.operator@finance.test',
        'password' => 'Correct-Password-1!',
    ])->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('mandatory two factor redirects admins to the security page', function () {
    $superAdmin = policySuperAdmin();

    app(SettingsService::class)->putMany([
        'security.two_factor_required_admin' => true,
    ], $superAdmin, 'Enforce 2FA for admins');

    $admin = User::factory()->create(['role' => UserRole::Admin]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertRedirect(route('security.edit'));

    // Super Admin is not covered by the admin-only requirement.
    $this->actingAs($superAdmin)
        ->get(route('dashboard'))
        ->assertRedirect(route('super-admin.dashboard'));

    $enrolled = User::factory()->withTwoFactor()->create(['role' => UserRole::Admin]);

    $this->actingAs($enrolled)
        ->get(route('dashboard'))
        ->assertRedirect(route('admin.dashboard'));
});

test('sessions can be revoked with ownership protection', function () {
    $superAdmin = policySuperAdmin();
    $admin = User::factory()->create();

    DB::table('sessions')->insert([
        'id' => 'test-session-'.$admin->id,
        'user_id' => $admin->id,
        'ip_address' => '10.0.0.9',
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0) Chrome/120.0',
        'payload' => base64_encode(json_encode([])),
        'last_activity' => time(),
    ]);

    $sessions = app(SessionService::class);

    // A session belonging to another user cannot be revoked through the
    // owner-scoped method.
    expect($sessions->revoke('test-session-'.$admin->id, $superAdmin))->toBeFalse();

    $this->actingAs($superAdmin);

    expect($sessions->revokeAnyExceptCurrent('test-session-'.$admin->id))->toBeTrue()
        ->and(DB::table('sessions')->where('id', 'test-session-'.$admin->id)->exists())->toBeFalse();
});

test('session timeout resolves from the security settings', function () {
    $superAdmin = policySuperAdmin();

    app(SettingsService::class)->put('security.session_timeout_minutes', 45, $superAdmin, 'Tighter session window');

    expect(app(SessionService::class)->timeoutMinutes())->toBe(45);
});

test('backup configuration reports the real run outcome', function () {
    $service = app(DatabaseBackupService::class);

    expect($service->isDue())->toBeFalse();

    $superAdmin = policySuperAdmin();

    app(SettingsService::class)->putMany([
        'backup.enabled' => true,
        'backup.frequency' => 'daily',
    ], $superAdmin, 'Enable backups');

    expect($service->isDue())->toBeTrue();

    $result = $service->run();

    // The test database is in-memory SQLite, which cannot be dumped: the
    // failure is recorded honestly instead of reporting a fake success.
    expect($result['status'])->toBe('failed')
        ->and(app(SettingsService::class)->get('backup.last_status'))->toBe('failed')
        ->and($service->isDue())->toBeFalse();
});

test('audit logging can be switched off', function () {
    $superAdmin = policySuperAdmin();

    app(SettingsService::class)->put('security.audit_logging_enabled', false, $superAdmin, 'Maintenance window');

    $user = User::factory()->create();

    AuditLogger::log('test.audit_toggle', $user, [], ['value' => 'never']);

    $this->assertDatabaseMissing('audit_logs', ['action' => 'test.audit_toggle']);
});
