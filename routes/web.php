<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\OtpVerificationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KycDocumentController;
use App\Http\Controllers\VerificationController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

/*
|--------------------------------------------------------------------------
| Passwordless authentication
|--------------------------------------------------------------------------
|
| Admins and Super Admins sign in with their registered mobile number and a
| one-time code. Nothing is authenticated until that OTP is verified.
|
*/
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLogin'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:login')->name('login.store');

    Route::get('login/otp', [OtpVerificationController::class, 'show'])->name('login.otp');
    Route::post('login/otp', [OtpVerificationController::class, 'verify'])->middleware('throttle:otp-verify')->name('login.otp.verify');
    Route::post('login/otp/resend', [OtpVerificationController::class, 'resend'])->middleware('throttle:otp-resend')->name('login.otp.resend');
    Route::post('login/otp/cancel', [OtpVerificationController::class, 'cancel'])->name('login.otp.cancel');
});

Route::middleware(['auth'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
});

Route::middleware(['auth', 'role:super_admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'superAdmin'])->name('dashboard');

    Route::livewire('users', 'pages::users.index')->name('users.index');
    Route::livewire('audit-logs', 'pages::audit-logs.index')->name('audit-logs.index');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('dashboard', [DashboardController::class, 'admin'])->name('dashboard');
});

Route::middleware(['auth', 'role:super_admin,admin'])->group(function () {
    Route::livewire('how-it-works', 'pages::how-it-works.index')->name('how-it-works.index');

    Route::livewire('borrowers', 'pages::borrowers.index')->name('borrowers.index');
    Route::livewire('borrowers/create', 'pages::borrowers.form')->name('borrowers.create');
    Route::livewire('borrowers/{borrower}/edit', 'pages::borrowers.form')->name('borrowers.edit');
    Route::livewire('borrowers/{borrower}', 'pages::borrowers.show')->name('borrowers.show');

    Route::livewire('guarantors', 'pages::guarantors.index')->name('guarantors.index');
    Route::livewire('guarantors/{guarantor}', 'pages::guarantors.show')->name('guarantors.show');

    Route::livewire('loans', 'pages::loans.index')->name('loans.index');
    Route::livewire('loans/create', 'pages::loans.create')->name('loans.create');
    Route::livewire('loans/{loan}', 'pages::loans.show')->name('loans.show');
    Route::livewire('loans/{loan}/calculator', 'pages::loans.calculator')->name('loans.calculator');
    Route::livewire('loans/{loan}/capital-verification', 'pages::loans.capital-verification')->name('loans.capital-verification');
    Route::livewire('loans/{loan}/disbursement', 'pages::loans.disbursement')->name('loans.disbursement');

    Route::livewire('collections', 'pages::collections.index')->name('collections.index');
    Route::livewire('operations', 'pages::operations.index')->name('operations.index');
    Route::livewire('investors', 'pages::investors.index')->name('investors.index');
    Route::livewire('investors/create', 'pages::investors.form')->name('investors.create');
    Route::livewire('investors/{investor}/edit', 'pages::investors.form')->name('investors.edit');
    Route::livewire('investors/{investor}', 'pages::investors.show')->name('investors.show');
    Route::livewire('investor-payouts', 'pages::payouts.index')->name('payouts.index');
    Route::livewire('investor-payouts/history', 'pages::payouts.history')->name('payouts.history');
    Route::livewire('admin-commissions', 'pages::admin-commissions.index')->name('admin-commissions.index');
    Route::livewire('company-earnings', 'pages::company-earnings.index')->name('company-earnings.index');
    Route::livewire('distribution-settings', 'pages::distribution.settings')->name('distribution.settings');
    Route::livewire('loans/{loan}/distribution', 'pages::loans.distribution')->name('loans.distribution');
    Route::livewire('foreclosure-requests', 'pages::loans.foreclosure-requests')->name('foreclosure.requests');

    Route::livewire('reports', 'pages::reports.index')->name('reports.index');

    Route::get('documents/{document}/download', [KycDocumentController::class, 'download'])->name('documents.download');
    Route::get('documents/{document}/preview', [VerificationController::class, 'preview'])->name('documents.preview');
});

Route::middleware(['auth', 'role:super_admin,admin'])->prefix('super-admin')->name('super-admin.')->group(function () {
    Route::livewire('verification/borrowers', 'pages::verification.index')->name('verification.borrowers');
    Route::livewire('verification/investors', 'pages::verification.index')->name('verification.investors');
    Route::livewire('verification/{type}/{subject}', 'pages::verification.show')->name('verification.show');

    Route::livewire('deposit-allocation', 'pages::deposit-allocation.index')->name('deposit-allocation.index');
});

require __DIR__.'/settings.php';
