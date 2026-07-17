<?php

namespace Database\Factories;

use App\Models\SpokePlan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SpokePlan>
 */
class SpokePlanFactory extends Factory
{
    protected $model = SpokePlan::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => ucfirst($name),
            'slug' => Str::slug($name),
            'price_cents' => fake()->randomElement([null, 0, 1999, 4999, 9999]),
            'features' => [
                fake()->sentence(),
                fake()->sentence(),
            ],
            'sort_order' => 0,
        ];
    }
}
