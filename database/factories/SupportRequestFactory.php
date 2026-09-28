<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\Category;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportRequest>
 */
class SupportRequestFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(6),
            'description' => fake()->paragraph(),
            'category_id' => Category::factory(),
            'requester_id' => User::factory(),
            'status' => RequestStatus::New,
        ];
    }
}
