<?php

use App\Http\Controllers\Settings\GeneralSettingsController;
use App\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
    Route::get('settings/profile/avatar', [ProfileController::class, 'avatar'])->name('profile.avatar');

    Route::livewire('settings/notifications', 'pages::settings.notifications')->name('settings.notifications');
    Route::livewire('settings/preferences', 'pages::settings.preferences')->name('settings.preferences');

    Route::livewire('settings/security', 'pages::settings.security')
        ->name('security.edit');
});

// Super Admin settings. View routes honour explicitly granted permissions;
// every mutation re-checks authorization inside the request or component.
Route::middleware(['auth'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::livewire('settings', 'pages::settings.index')->middleware('role:super_admin')->name('settings');

    Route::get('settings/general', [GeneralSettingsController::class, 'edit'])
        ->middleware('permission:settings.view_general,settings.modify')
        ->name('settings.general');
    Route::put('settings/general', [GeneralSettingsController::class, 'update'])
        ->middleware('permission:settings.modify')
        ->name('settings.general.update');

    Route::livewire('settings/loans', 'pages::settings.loans')
        ->middleware('permission:settings.view_loan,settings.modify')
        ->name('settings.loans');

    Route::livewire('settings/interest', 'pages::settings.interest')
        ->middleware('permission:settings.view_interest,settings.modify')
        ->name('settings.interest');

    Route::livewire('settings/investors', 'pages::settings.investors')
        ->middleware('role:super_admin')
        ->name('settings.investors');

    Route::livewire('settings/kyc', 'pages::settings.kyc')
        ->middleware('role:super_admin')
        ->name('settings.kyc');

    Route::livewire('settings/security', 'pages::settings.system-security')
        ->middleware('role:super_admin')
        ->name('settings.security');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::livewire('settings', 'pages::settings.index')->name('settings');
});
