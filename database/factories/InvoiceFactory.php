<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $items = [
            [
                'description' => fake()->words(3, true),
                'quantity' => rand(1, 10),
                'unit_price' => fake()->randomFloat(2, 10, 500),
            ],
            [
                'description' => fake()->words(3, true),
                'quantity' => rand(1, 5),
                'unit_price' => fake()->randomFloat(2, 20, 300),
            ],
        ];

        $subtotal = array_sum(array_map(fn ($item) => $item['quantity'] * $item['unit_price'], $items));
        $taxRate = fake()->randomElement([0, 10, 16, 21]);
        $total = round($subtotal * (1 + $taxRate / 100), 2);

        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'invoice_number' => 'INV-'.now()->year.'-'.fake()->unique()->numberBetween(1, 9999),
            'client_name' => fake()->company(),
            'client_email' => fake()->email(),
            'issue_date' => now()->subDays(rand(1, 30)),
            'due_date' => now()->addDays(rand(15, 60)),
            'status' => fake()->randomElement(['draft', 'sent', 'paid']),
            'items' => $items,
            'notes' => fake()->optional()->sentence(),
            'subtotal' => $subtotal,
            'tax_rate' => $taxRate,
            'total' => $total,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }

    public function sent(): static
    {
        return $this->state(fn () => ['status' => 'sent']);
    }

    public function paid(): static
    {
        return $this->state(fn () => ['status' => 'paid']);
    }
}
