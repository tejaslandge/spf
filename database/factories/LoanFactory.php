<?php

namespace Database\Factories;

use App\Enums\InterestMethod;
use App\Enums\LoanStatus;
use App\Models\Borrower;
use App\Models\Loan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    protected $model = Loan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $principal = fake()->numberBetween(50_000, 500_000);

        return [
            'borrower_id' => Borrower::factory(),
            'principal_amount' => $principal,
            'annual_interest_rate' => 12,
            'interest_method' => InterestMethod::Reducing,
            'tenure_months' => 12,
            'emi_frequency' => 'monthly',
            'disbursement_date' => null,
            'first_emi_date' => now()->addMonth()->startOfMonth(),
            'emi_amount' => 0,
            'total_interest' => 0,
            'total_payable' => 0,
            'outstanding_principal' => $principal,
            'outstanding_interest' => 0,
            'status' => LoanStatus::Pending,
            'notes' => null,
        ];
    }

    public function active(): static
    {
        return $this->state(fn () => [
            'status' => LoanStatus::Active,
            'disbursement_date' => now()->startOfMonth(),
        ]);
    }
}
