<?php

use App\Http\Controllers\Api\AdminCommissionPayoutController;
use App\Http\Controllers\Api\AdminCommissionProfileController;
use App\Http\Controllers\Api\CompanyEarningsController;
use App\Http\Controllers\Api\EmiDistributionController;
use App\Http\Controllers\Api\InterestConfigurationController;
use App\Http\Controllers\Api\InvestorInterestRateController;
use App\Http\Controllers\Api\InvestorPayoutController;
use App\Http\Controllers\Api\LoanDistributionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'role:super_admin,admin'])
    ->prefix('v1')
    ->name('api.v1.')
    ->group(function (): void {
        Route::apiResource('interest-configurations', InterestConfigurationController::class)->except('show');

        Route::get('investor-interest-rates', [InvestorInterestRateController::class, 'index'])->name('investor-interest-rates.index');
        Route::post('investor-interest-rates', [InvestorInterestRateController::class, 'store'])->name('investor-interest-rates.store');
        Route::delete('investor-interest-rates/{rate}', [InvestorInterestRateController::class, 'destroy'])->name('investor-interest-rates.destroy');

        Route::get('admin-commission-profiles', [AdminCommissionProfileController::class, 'index'])->name('admin-commission-profiles.index');
        Route::post('admin-commission-profiles', [AdminCommissionProfileController::class, 'store'])->name('admin-commission-profiles.store');
        Route::delete('admin-commission-profiles/{profile}', [AdminCommissionProfileController::class, 'destroy'])->name('admin-commission-profiles.destroy');

        Route::get('investor-payouts', [InvestorPayoutController::class, 'index'])->name('investor-payouts.index');
        Route::post('investor-payouts/generate', [InvestorPayoutController::class, 'generate'])->name('investor-payouts.generate');
        Route::post('investor-payouts/{payout}/approve', [InvestorPayoutController::class, 'approve'])->name('investor-payouts.approve');
        Route::post('investor-payouts/{payout}/reject', [InvestorPayoutController::class, 'reject'])->name('investor-payouts.reject');
        Route::post('investor-payouts/{payout}/process', [InvestorPayoutController::class, 'process'])->name('investor-payouts.process');
        Route::post('investor-payouts/{payout}/cancel', [InvestorPayoutController::class, 'cancel'])->name('investor-payouts.cancel');

        Route::get('admin-commission-payouts', [AdminCommissionPayoutController::class, 'index'])->name('admin-commission-payouts.index');
        Route::post('admin-commission-payouts/generate', [AdminCommissionPayoutController::class, 'generate'])->name('admin-commission-payouts.generate');
        Route::post('admin-commission-payouts/{payout}/approve', [AdminCommissionPayoutController::class, 'approve'])->name('admin-commission-payouts.approve');
        Route::post('admin-commission-payouts/{payout}/reject', [AdminCommissionPayoutController::class, 'reject'])->name('admin-commission-payouts.reject');
        Route::post('admin-commission-payouts/{payout}/process', [AdminCommissionPayoutController::class, 'process'])->name('admin-commission-payouts.process');
        Route::post('admin-commission-payouts/{payout}/cancel', [AdminCommissionPayoutController::class, 'cancel'])->name('admin-commission-payouts.cancel');

        Route::get('company-earnings', [CompanyEarningsController::class, 'index'])->name('company-earnings.index');

        Route::get('loans/{loan}/distribution', [LoanDistributionController::class, 'show'])->name('loans.distribution.show');
        Route::get('loans/{loan}/distribution/entries', [LoanDistributionController::class, 'entries'])->name('loans.distribution.entries');
        Route::post('loans/{loan}/distribution/snapshot', [LoanDistributionController::class, 'snapshot'])->name('loans.distribution.snapshot');

        Route::post('emi-payments/{payment}/distribute', [EmiDistributionController::class, 'distribute'])->name('emi-payments.distribute');
        Route::post('emi-payments/{payment}/reverse', [EmiDistributionController::class, 'reverse'])->name('emi-payments.reverse');
    });
