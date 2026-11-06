<?php

namespace App\Support;

use App\Enums\InterestMethod;
use Carbon\CarbonImmutable;

final class LoanCalculator
{
    /**
     * Build an amortization plan without touching the database.
     *
     * All monetary values are decimal strings rounded to 2 places.
     */
    public function calculate(
        string|int|float $principal,
        string|int|float $annualRate,
        int $tenureMonths,
        InterestMethod $method,
        CarbonImmutable|string $firstEmiDate,
    ): LoanCalculation {
        if ($tenureMonths < 1) {
            throw new \InvalidArgumentException('Tenure must be at least one month.');
        }

        if (Money::compare($principal, '0') <= 0) {
            throw new \InvalidArgumentException('Principal must be greater than zero.');
        }

        $principal = Money::round($principal);
        $annualRate = (string) $annualRate;
        $firstEmiDate = $firstEmiDate instanceof CarbonImmutable
            ? $firstEmiDate
            : CarbonImmutable::parse($firstEmiDate);

        $rows = $method === InterestMethod::Flat
            ? $this->flatSchedule($principal, $annualRate, $tenureMonths)
            : $this->reducingSchedule($principal, $annualRate, $tenureMonths);

        $installments = [];
        $outstanding = $principal;
        $totalInterest = '0.00';

        foreach ($rows as $index => $row) {
            $outstanding = Money::round(Money::sub($outstanding, $row['principal']));
            $totalInterest = Money::round(Money::add($totalInterest, $row['interest']));

            $installments[] = new LoanInstallment(
                installmentNo: $index + 1,
                dueDate: $firstEmiDate->addMonthsNoOverflow($index),
                principal: $row['principal'],
                interest: $row['interest'],
                total: Money::round(Money::add($row['principal'], $row['interest'])),
                outstandingPrincipal: $outstanding,
            );
        }

        $totalPrincipal = Money::round(Money::add(...array_map(fn (LoanInstallment $i) => $i->principal, $installments)));
        $totalPayable = Money::round(Money::add(...array_map(fn (LoanInstallment $i) => $i->total, $installments)));

        return new LoanCalculation(
            method: $method,
            principal: $principal,
            annualRate: $annualRate,
            tenureMonths: $tenureMonths,
            emiAmount: $installments[0]->total,
            totalInterest: $totalInterest,
            totalPayable: $totalPayable,
            totalPrincipal: $totalPrincipal,
            installments: $installments,
        );
    }

    /**
     * @return array<int, array{principal: string, interest: string}>
     */
    private function flatSchedule(string $principal, string $annualRate, int $months): array
    {
        $totalInterest = Money::round(
            Money::div(Money::mul($principal, $annualRate, (string) $months), '1200')
        );
        $basePrincipal = Money::div($principal, (string) $months);
        $baseInterest = Money::div($totalInterest, (string) $months);

        $rows = [];
        $principalPaid = '0.00';
        $interestPaid = '0.00';

        for ($month = 1; $month <= $months; $month++) {
            $isLast = $month === $months;

            $rowPrincipal = $isLast
                ? Money::sub($principal, $principalPaid)
                : Money::round($basePrincipal);

            $rowInterest = $isLast
                ? Money::sub($totalInterest, $interestPaid)
                : Money::round($baseInterest);

            $principalPaid = Money::add($principalPaid, $rowPrincipal);
            $interestPaid = Money::add($interestPaid, $rowInterest);

            $rows[] = ['principal' => $rowPrincipal, 'interest' => $rowInterest];
        }

        return $rows;
    }

    /**
     * @return array<int, array{principal: string, interest: string}>
     */
    private function reducingSchedule(string $principal, string $annualRate, int $months): array
    {
        $monthlyRate = bcdiv($annualRate, '1200', 12);
        $emi = $this->reducingEmi($principal, $monthlyRate, $months);

        $rows = [];
        $outstanding = $principal;

        for ($month = 1; $month <= $months; $month++) {
            $isLast = $month === $months;

            $interest = Money::round(bcmul($outstanding, $monthlyRate, 10));

            if ($isLast) {
                $rowPrincipal = $outstanding;
            } else {
                $rowPrincipal = Money::sub($emi, $interest);

                if (Money::compare($rowPrincipal, $outstanding) > 0) {
                    $rowPrincipal = $outstanding;
                }
            }

            $outstanding = Money::sub($outstanding, $rowPrincipal);

            $rows[] = ['principal' => $rowPrincipal, 'interest' => $interest];
        }

        return $rows;
    }

    private function reducingEmi(string $principal, string $monthlyRate, int $months): string
    {
        if (Money::isZero($monthlyRate)) {
            return Money::round(Money::div($principal, (string) $months));
        }

        $growth = bcpow(bcadd('1', $monthlyRate, 12), (string) $months, 12);
        $numerator = bcmul(bcmul($principal, $monthlyRate, 12), $growth, 12);
        $denominator = bcsub($growth, '1', 12);

        return Money::round(bcdiv($numerator, $denominator, 12));
    }
}
