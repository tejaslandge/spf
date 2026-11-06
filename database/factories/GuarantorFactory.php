<?php

namespace Database\Factories;

use App\Enums\IdentityDocumentType;
use App\Enums\RelationshipType;
use App\Models\Guarantor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Guarantor>
 */
class GuarantorFactory extends Factory
{
    protected $model = Guarantor::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $relationship = fake()->randomElement(RelationshipType::cases());
        $identityType = fake()->randomElement(IdentityDocumentType::cases());

        return [
            'full_name' => fake()->name(),
            'mobile_number' => fake()->unique()->numerify('9#########'),
            'alternate_mobile' => null,
            'email' => fake()->safeEmail(),
            'address' => fake()->streetAddress().', '.fake()->city(),
            'city' => fake()->city(),
            'state' => fake()->randomElement(['Delhi', 'Gujarat', 'Karnataka', 'Maharashtra', 'Rajasthan', 'Tamil Nadu']),
            'pincode' => fake()->numerify('1#####'),
            'relationship' => $relationship,
            'custom_relationship' => $relationship === RelationshipType::Other ? 'Neighbour' : null,
            'occupation' => fake()->jobTitle(),
            'identity_document_type' => $identityType,
            'identity_document_number' => strtoupper(fake()->bothify('####-####-####')),
            'notes' => null,
        ];
    }
}
