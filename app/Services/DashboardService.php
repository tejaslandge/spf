<?php

namespace App\Services;

use App\Enums\LoanFundingStatus;
use App\Enums\LoanStatus;
use App\Enums\PayoutStatus;
use App\Models\AuditLog;
use App\Models\EmiPayment;
use App\Models\EmiSchedule;
use App\Models\Investor;
use App\Models\InvestorAllocation;
use App\Models\InvestorPayout;
use App\Models\Loan;
use App\Models\User;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class DashboardService
{
    /**
     * @return array<string, mixed>
     */
    public function admin(): array
    {
        $today = CarbonImmutable::today();
        $months = $this->lastSixMonths();

        return [
            'active_loans' => Loan::query()->where('status', LoanStatus::Active)->count(),
            'portfolio_outstanding' => (string) Loan::query()->where('status', LoanStatus::Active)->sum('outstanding_principal'),
            'disbursed_total' => (string) Loan::query()->whereNotNull('disbursement_date')->sum('principal_amount'),
            'collected_this_month' => (string) EmiPayment::query()
                ->whereBetween('payment_date', [$today->startOfMonth(), $today->endOfMonth()])
                ->sum('amount'),
            'overdue_count' => EmiSchedule::query()->overdue()->count(),
            'awaiting_funding_count' => Loan::query()
                ->where('status', LoanStatus::Pending)
                ->where('funding_status', '!=', LoanFundingStatus::FullyFunded->value)
                ->count(),
            'awaiting_funding_amount' => (string) Loan::query()
                ->where('status', LoanStatus::Pending)
                ->where('funding_status', '!=', LoanFundingStatus::FullyFunded->value)
                ->sum('principal_amount'),
            'overdue_amount' => (string) EmiSchedule::query()->overdue()->sum('total_amount'),
            'due_soon_count' => EmiSchedule::query()->unsettled()
                ->whereBetween('due_date', [$today, $today->addDays(7)])
                ->count(),
            'due_soon_amount' => (string) EmiSchedule::query()->unsettled()
                ->whereBetween('due_date', [$today, $today->addDays(7)])
                ->sum('total_amount'),
            'upcoming' => EmiSchedule::query()
                ->with('loan.borrower')
                ->unsettled()
                ->orderBy('due_date')
                ->limit(6)
                ->get(),
            'recent_payments' => EmiPayment::query()
                ->with('loan.borrower')
                ->latest('payment_date')
                ->limit(6)
                ->get(),
            'recent_loans' => Loan::query()
                ->with('borrower')
                ->latest()
                ->limit(5)
                ->get(),
            'collection_series' => $this->collectionSeries($months),
            'months' => $months->map(fn (CarbonImmutable $month) => $month->format('M'))->all(),
            'status_mix' => Loan::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function superAdmin(): array
    {
        $today = CarbonImmutable::today();
        $months = $this->lastSixMonths();

        $pendingPayouts = InvestorPayout::query()->where('status', PayoutStatus::Pending);

        return [
            'total_capital' => (string) InvestorAllocation::query()->where('status', 'active')->sum('principal_amount'),
            'investors_count' => Investor::query()->where('status', 'active')->count(),
            'active_loans' => Loan::query()->where('status', LoanStatus::Active)->count(),
            'portfolio_outstanding' => (string) Loan::query()->where('status', LoanStatus::Active)->sum('outstanding_principal'),
            'interest_earned' => (string) EmiPayment::query()->sum('interest_component'),
            'collected_this_month' => (string) EmiPayment::query()
                ->whereBetween('payment_date', [$today->startOfMonth(), $today->endOfMonth()])
                ->sum('amount'),
            'pending_payouts_count' => (clone $pendingPayouts)->count(),
            'pending_payouts_amount' => (string) (clone $pendingPayouts)->sum('net_amount'),
            'team_count' => User::query()->count(),
            'collection_series' => $this->collectionSeries($months),
            'months' => $months->map(fn (CarbonImmutable $month) => $month->format('M'))->all(),
            'recent_audits' => AuditLog::query()->with('user')->latest()->limit(8)->get(),
            'recent_payouts' => InvestorPayout::query()->with('investor')->latest()->limit(5)->get(),
            'distribution' => app(DistributionReportService::class)->summary(),
            'status_mix' => Loan::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
        ];
    }

    /**
     * @return Collection<int, CarbonImmutable>
     */
    private function lastSixMonths(): Collection
    {
        $start = CarbonImmutable::today()->startOfMonth()->subMonths(5);

        return collect(range(0, 5))->map(fn (int $offset) => $start->addMonthsNoOverflow($offset));
    }

    /**
     * @param  Collection<int, CarbonImmutable>  $months
     * @return array<int, float>
     */
    private function collectionSeries(Collection $months): array
    {
        return $months->map(fn (CarbonImmutable $month) => (float) EmiPayment::query()
            ->whereBetween('payment_date', [$month->startOfMonth(), $month->endOfMonth()])
            ->sum('amount'))->all();
    }

    public static function money(string|int|float|null $value): string
    {
        return Money::format($value);
    }
}
