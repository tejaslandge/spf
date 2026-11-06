<?php

use App\Models\User;
use App\Services\Auth\LoginOtpService;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    config([
        'services.renflair.url' => 'https://sms.renflair.in/V1.php',
        'services.renflair.api_key' => 'test-api-key',
    ]);

    Http::preventStrayRequests();
});

test('inactive users cannot log in', function () {
    Http::fake();

    $user = User::factory()->create(['status' => 'inactive']);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrorsIn('email');

    $this->assertGuest();

    Http::assertNothingSent();
});

test('users without a registered mobile number cannot start otp login', function () {
    Http::fake();

    $user = User::factory()->create(['mobile' => null]);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrorsIn('email');

    $this->assertGuest();

    Http::assertNothingSent();
});

test('a missing gateway configuration still shows the otp screen and the logged code works', function () {
    config([
        'services.renflair.api_key' => '',
        'services.renflair.log_otp' => true,
    ]);

    Http::fake();

    $loggedOtp = null;

    Log::listen(function (MessageLogged $event) use (&$loggedOtp) {
        if (($event->context['otp'] ?? null) !== null) {
            $loggedOtp = (string) $event->context['otp'];
        }
    });

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('login.otp'));

    $this->assertGuest();

    expect(session(LoginOtpService::SESSION_KEY))->toBeArray();

    $this->get(route('login.otp'))
        ->assertOk()
        ->assertSee('SMS delivery is not configured');

    Http::assertNothingSent();

    expect($loggedOtp)->toMatch('/^\d{6}$/');

    $this->post(route('login.otp.verify'), ['code' => $loggedOtp])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('dashboards are not accessible before otp verification', function () {
    Http::fake([
        '*sms.renflair.in*' => Http::response(['success' => true]),
    ]);

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('login.otp'));

    $this->assertGuest();

    $this->get(route('dashboard'))->assertRedirect(route('login'));
});

test('a valid otp authenticates the user and redirects to the role dashboard', function () {
    Http::fake([
        '*sms.renflair.in*' => Http::response(['success' => true]),
    ]);

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('login.otp'));

    $this->post(route('login.otp.verify'), [
        'code' => lastRenflairOtp(),
    ])->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($user);

    expect(session(LoginOtpService::SESSION_KEY))->toBeNull();
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

test('super admins are redirected to the super admin dashboard after otp verification', function () {
    Http::fake([
        '*sms.renflair.in*' => Http::response(['success' => true]),
    ]);

    $user = User::factory()->superAdmin()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->post(route('login.otp.verify'), [
        'code' => lastRenflairOtp(),
    ])->assertRedirect(route('super-admin.dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('an incorrect otp does not authenticate the user', function () {
    Http::fake([
        '*sms.renflair.in*' => Http::response(['success' => true]),
    ]);

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response = $this->post(route('login.otp.verify'), [
        'code' => otpDifferentFrom(lastRenflairOtp()),
    ]);

    $response->assertSessionHasErrors('code');

    $this->assertGuest();

    expect(session(LoginOtpService::SESSION_KEY))->toBeArray();
    expect(session(LoginOtpService::SESSION_KEY)['attempts'])->toBe(1);
});

test('an expired otp challenge cannot authenticate', function () {
    Http::fake([
        '*sms.renflair.in*' => Http::response(['success' => true]),
    ]);

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $code = lastRenflairOtp();

    $this->travel(LoginOtpService::EXPIRES_AFTER_SECONDS + 1)->seconds();

    $this->post(route('login.otp.verify'), [
        'code' => $code,
    ])->assertRedirect(route('login'));

    $this->assertGuest();

    expect(session(LoginOtpService::SESSION_KEY))->toBeNull();

    // Only the original send request was made; verification is local.
    Http::assertSentCount(1);
});

test('the maximum number of otp attempts is enforced', function () {
    Http::fake([
        '*sms.renflair.in*' => Http::response(['success' => true]),
    ]);

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $wrong = otpDifferentFrom(lastRenflairOtp());

    for ($attempt = 1; $attempt < LoginOtpService::MAX_ATTEMPTS; $attempt++) {
        $this->post(route('login.otp.verify'), ['code' => $wrong])
            ->assertSessionHasErrors('code');
    }

    $this->post(route('login.otp.verify'), ['code' => $wrong])
        ->assertRedirect(route('login'));

    $this->assertGuest();

    expect(session(LoginOtpService::SESSION_KEY))->toBeNull();
});

test('otp resend has a 60 second cooldown', function () {
    Http::fake([
        '*sms.renflair.in*' => Http::response(['success' => true]),
    ]);

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $firstCode = lastRenflairOtp();

    $this->post(route('login.otp.resend'))
        ->assertSessionHasErrors('resend');

    Http::assertSentCount(1);

    $this->travel(LoginOtpService::RESEND_COOLDOWN_SECONDS + 1)->seconds();

    $this->post(route('login.otp.resend'))
        ->assertSessionHasNoErrors();

    Http::assertSentCount(2);

    expect(lastRenflairOtp())->not->toBe($firstCode);
});

test('resending invalidates the previous code', function () {
    Http::fake([
        '*sms.renflair.in*' => Http::response(['success' => true]),
    ]);

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $firstCode = lastRenflairOtp();
    $firstChallengeId = session(LoginOtpService::SESSION_KEY)['id'];

    $this->travel(LoginOtpService::RESEND_COOLDOWN_SECONDS + 1)->seconds();

    $this->post(route('login.otp.resend'));

    $secondCode = lastRenflairOtp();
    $secondChallengeId = session(LoginOtpService::SESSION_KEY)['id'];

    expect($secondChallengeId)->not->toBe($firstChallengeId);
    expect($secondCode)->not->toBe($firstCode);

    $this->post(route('login.otp.verify'), ['code' => $firstCode])
        ->assertSessionHasErrors('code');

    $this->assertGuest();

    $this->post(route('login.otp.verify'), ['code' => $secondCode])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('the session id is regenerated after successful verification', function () {
    Http::fake([
        '*sms.renflair.in*' => Http::response(['success' => true]),
    ]);

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $sessionIdBeforeVerification = session()->getId();

    $this->post(route('login.otp.verify'), ['code' => lastRenflairOtp()]);

    expect(session()->getId())->not->toBe($sessionIdBeforeVerification);
});

test('the otp is stored as a hash and never in plaintext', function () {
    Http::fake([
        '*sms.renflair.in*' => Http::response(['success' => true]),
    ]);

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $code = lastRenflairOtp();
    $challenge = session(LoginOtpService::SESSION_KEY);

    expect($challenge)->not->toHaveKey('otp');
    expect($challenge['otp_hash'])->not->toBe($code);
});

test('the otp is sent to the registered mobile number only', function () {
    Http::fake([
        '*sms.renflair.in*' => Http::response(['success' => true]),
    ]);

    $user = User::factory()->create(['mobile' => '9876543210']);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    Http::assertSent(function ($request) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['PHONE'] ?? null) === '9876543210'
            && preg_match('/^\d{6}$/', (string) ($query['OTP'] ?? '')) === 1;
    });

    $this->post(route('login.otp.verify'), [
        'code' => lastRenflairOtp(),
        'phone' => '+919999999999',
    ])->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('otp verification is local and makes no verification api call', function () {
    Http::fake([
        '*sms.renflair.in*' => Http::response(['success' => true]),
    ]);

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->post(route('login.otp.verify'), ['code' => lastRenflairOtp()])
        ->assertRedirect(route('admin.dashboard'));

    // Only the outbound SMS request was made.
    Http::assertSentCount(1);
});

test('the otp page masks the registered mobile number', function () {
    Http::fake([
        '*sms.renflair.in*' => Http::response(['success' => true]),
    ]);

    $user = User::factory()->create(['mobile' => '9876543210']);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->get(route('login.otp'))
        ->assertOk()
        ->assertSee('+91 XXXXXXX210');
});

test('otp is required before the otp screen can be shown', function () {
    $this->get(route('login.otp'))->assertRedirect(route('login'));
});

test('a sms gateway failure still shows the otp screen and the code remains usable', function () {
    config(['services.renflair.log_otp' => true]);

    Http::fake([
        '*sms.renflair.in*' => Http::response(['message' => 'Provider unavailable'], 500),
    ]);

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('login.otp'));

    $this->assertGuest();

    expect(session(LoginOtpService::SESSION_KEY))->toBeArray();

    $this->get(route('login.otp'))
        ->assertOk()
        ->assertSee('could not send the SMS');

    $this->post(route('login.otp.verify'), ['code' => lastRenflairOtp()])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('a rejected sms gateway response still shows the otp screen and the code remains usable', function () {
    config(['services.renflair.log_otp' => true]);

    Http::fake([
        '*sms.renflair.in*' => Http::response(['success' => false, 'error' => 'Invalid API key']),
    ]);

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('login.otp'));

    $this->assertGuest();

    expect(session(LoginOtpService::SESSION_KEY))->toBeArray();

    $this->get(route('login.otp'))
        ->assertOk()
        ->assertSee('could not send the SMS');

    $this->post(route('login.otp.verify'), ['code' => lastRenflairOtp()])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($user);
});

test('the otp is written to the application log when logging is enabled', function () {
    config(['services.renflair.log_otp' => true]);

    Http::fake([
        '*sms.renflair.in*' => Http::response(['success' => true]),
    ]);

    $loggedOtp = null;
    $loggedPhone = null;

    Log::listen(function (MessageLogged $event) use (&$loggedOtp, &$loggedPhone) {
        if ($event->message === 'Login OTP code generated.') {
            $loggedOtp = (string) ($event->context['otp'] ?? '');
            $loggedPhone = (string) ($event->context['phone'] ?? '');
        }
    });

    $user = User::factory()->create(['mobile' => '9876543210']);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    expect($loggedOtp)->toMatch('/^\d{6}$/');
    expect($loggedOtp)->toBe(lastRenflairOtp());
    expect($loggedPhone)->toBe('+91 XXXXXXX210');
});

test('a failed resend keeps the previous code valid', function () {
    Http::fake([
        '*sms.renflair.in*' => Http::sequence()
            ->push(['success' => true])
            ->push(['success' => false, 'error' => 'Gateway error']),
    ]);

    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $code = lastRenflairOtp();

    $this->travel(LoginOtpService::RESEND_COOLDOWN_SECONDS + 1)->seconds();

    $this->post(route('login.otp.resend'))
        ->assertSessionHasErrors('resend');

    $this->assertGuest();

    $this->post(route('login.otp.verify'), ['code' => $code])
        ->assertRedirect(route('admin.dashboard'));

    $this->assertAuthenticatedAs($user);
});
