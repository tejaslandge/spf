<?php

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    config([
        'services.renflair.url' => 'https://sms.renflair.in/V1.php',
        'services.renflair.api_key' => 'test-api-key',
    ]);
});

test('two factor challenge redirects to login when not authenticated', function () {
    $response = $this->get(route('two-factor.login'));

    $response->assertRedirect(route('login'));
});

test('two factor challenge can be rendered', function () {
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

    $this->post(route('login.otp.verify'), [
        'code' => lastRenflairOtp(),
    ])->assertRedirect(route('two-factor.login'));
});
