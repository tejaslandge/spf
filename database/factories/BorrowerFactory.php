<?php

namespace Database\Factories;

use App\Enums\BorrowerStatus;
use App\Models\Borrower;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Borrower>
 */
class BorrowerFactory extends Factory
{
    protected $model = Borrower::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'full_name' => fake()->name(),
            'address' => fake()->address(),
            'mobile' => fake()->unique()->numerify('9#########'),
            'alternate_mobile' => null,
            'emergency_contact_name' => fake()->name(),
            'emergency_contact_number' => fake()->numerify('9#########'),
            'status' => BorrowerStatus::Active,
            'notes' => null,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['status' => BorrowerStatus::Inactive]);
    }

    public function blacklisted(): static
    {
        return $this->state(fn () => ['status' => BorrowerStatus::Blacklisted]);
    }
}
