<?php

namespace Database\Factories;

use App\Models\Spoke;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Spoke>
 */
class SpokeFactory extends Factory
{
    protected $model = Spoke::class;

    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'icon' => fake()->randomElement(Spoke::VALID_ICONS),
            'is_active' => true,
            'sort_order' => 0,
        ];
    }
}
