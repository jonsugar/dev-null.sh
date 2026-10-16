<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GitHubRepository;
use App\Models\GitHubWebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GitHubWebhookDelivery>
 */
final class GitHubWebhookDeliveryFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'delivery_id' => fake()->uuid(),
            'event' => 'push',
            'payload' => [],
            'github_repository_watch_id' => GitHubRepository::factory(),
            'processed_at' => null,
        ];
    }
}
