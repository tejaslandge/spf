<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated admins are redirected to the admin dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('admin.dashboard'));
});

test('authenticated super admins are redirected to the super admin dashboard', function () {
    $user = User::factory()->superAdmin()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('super-admin.dashboard'));
});

test('the authenticated layout includes the logout confirmation modal', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('data-modal="logout-confirmation"', false)
        ->assertSee('Log out of your account?');
});
