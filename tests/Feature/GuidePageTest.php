<?php

use App\Models\User;

test('guests are redirected to the login page from the guide', function () {
    $this->get(route('how-it-works.index'))->assertRedirect(route('login'));
});

test('admins can open the product guide', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('how-it-works.index'))
        ->assertOk()
        ->assertSee('How the platform moves money')
        ->assertSee('Eleven steps from application to closure')
        ->assertSee('How it works')
        ->assertDontSee('Audit Logs');
});

test('super admins can open the guide and see administration links', function () {
    $this->actingAs(User::factory()->superAdmin()->create());

    $this->get(route('how-it-works.index'))
        ->assertOk()
        ->assertSee('How the platform moves money')
        ->assertSee('Audit Logs')
        ->assertSee('Team & Access');
});
