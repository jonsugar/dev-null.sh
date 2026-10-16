<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GitHubRepository;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GitHubRepository>
 */
final class GitHubRepositoryFactory extends Factory
{
    protected $model = GitHubRepository::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'github_repository_id' => fake()->unique()->numberBetween(1_000_000, 999_999_999),
            'full_name' => mb_strtolower(fake()->userName().'/'.fake()->slug(2)),
            'branches' => ['main'],
            'webhook_secret' => fake()->sha256(),
        ];
    }
}
