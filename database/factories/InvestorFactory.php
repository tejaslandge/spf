<?php

namespace Database\Factories;

use App\Enums\InvestorStatus;
use App\Models\Investor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Investor>
 */
class InvestorFactory extends Factory
{
    protected $model = Investor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'address' => fake()->address(),
            'mobile' => fake()->unique()->numerify('9#########'),
            'email' => fake()->unique()->safeEmail(),
            'pan_number' => strtoupper(fake()->bothify('?????####?')),
            'aadhaar_number' => fake()->numberBetween(2, 9).fake()->numerify('###########'),
            'bank_name' => fake()->randomElement(['HDFC Bank', 'ICICI Bank', 'State Bank of India', 'Axis Bank']),
            'bank_account_number' => fake()->numerify('############'),
            'bank_ifsc' => strtoupper(fake()->bothify('????0######')),
            'status' => InvestorStatus::Active,
            'notes' => null,
        ];
    }
}
