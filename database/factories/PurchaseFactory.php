<?php

namespace Database\Factories;

use App\Enums\PaymentGateway;
use App\Enums\PurchaseStatus;
use App\Models\Formation;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'formation_id' => Formation::factory(),
            'amount' => 5500,
            'currency' => 'XOF',
            'gateway' => PaymentGateway::ManualMobileMoney,
            'payment_method' => 'tg-mixx',
            'payment_method_name' => 'Mixx by Yas',
            'payment_number' => '+228 72 24 02 31',
            'payment_beneficiary' => 'Ahiankonou Kokou Joseph',
            'country' => 'tg',
            'payer_phone' => '+2289'.fake()->numerify('#######'),
            'payer_name' => fake()->name(),
            'transaction_id' => Str::upper(Str::random(12)),
            'proof_path' => null,
            'proof_hash' => hash('sha256', Str::random(40)),
            'status' => PurchaseStatus::Pending,
        ];
    }

    public function status(PurchaseStatus $status): static
    {
        return $this->state(fn (array $attributes) => ['status' => $status]);
    }
}
