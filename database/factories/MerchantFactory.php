<?php

namespace Database\Factories;

use App\Models\Merchant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Merchant>
 */
class MerchantFactory extends Factory
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
            'business_name' => fake()->company(),
            'status' => Merchant::STATUS_ACTIVE,
            'live_enabled' => false,
        ];
    }

    public function live(): static
    {
        return $this->state(fn (array $attributes) => ['live_enabled' => true]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['status' => Merchant::STATUS_SUSPENDED]);
    }

    /**
     * Start with a balance (written straight to the column, without ledger entries).
     */
    public function withBalance(string $amount, string $mode = 'test'): static
    {
        return $this->afterCreating(function (Merchant $merchant) use ($amount, $mode) {
            $merchant->forceFill([$mode.'_balance' => $amount])->save();
        });
    }
}
