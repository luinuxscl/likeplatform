<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Document>
 */
class DocumentFactory extends Factory
{
    protected $model = Document::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(rand(2, 6));

        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title),
            'content' => fake()->paragraphs(rand(2, 5), true),
        ];
    }
}
