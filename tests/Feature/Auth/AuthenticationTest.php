<?php

use App\Models\User;
use App\Services\Auth\LoginOtpService;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

beforeEach(function () {
    config([
        'services.renflair.url' => 'https://sms.renflair.in/V1.php',
        'services.renflair.api_key' => 'test-api-key',
    ]);
});

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('valid credentials start the otp challenge without authenticating', function () {
    Http::fake([
        '*sms.renflair.in*' => Http::response(['success' => true]),
    ]);

    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('login.otp'));

    $this->assertGuest();

    expect(session(LoginOtpService::SESSION_KEY))->toBeArray();

    Http::assertSent(function ($request) use ($user) {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return ($query['PHONE'] ?? null) === PhoneNumber::national($user->mobile)
            && preg_match('/^\d{6}$/', (string) ($query['OTP'] ?? '')) === 1;
    });
});

test('users can not authenticate with invalid password', function () {
    Http::fake();

    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrorsIn('email');

    $this->assertGuest();

    Http::assertNothingSent();
});

test('users with two factor enabled are challenged after the otp is verified', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    Http::fake([
        '*sms.renflair.in*' => Http::response(['success' => true]),
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertRedirect(route('login.otp'));

    $response = $this->post(route('login.otp.verify'), [
        'code' => lastRenflairOtp(),
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});
