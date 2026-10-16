<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\GitHubRepository;
use App\Models\User;

final readonly class GitHubRepositoriesQuery
{
    /**
     * @return list<array{id: string, full_name: string, branches: list<string>, webhook_url: string, webhook_secret: string}>
     */
    public function handle(User $user): array
    {
        return array_values($user->githubRepositories()->orderBy('full_name')->get()
            ->map(fn (GitHubRepository $repository): array => [
                'id' => $repository->id,
                'full_name' => $repository->full_name,
                'branches' => $repository->branches,
                'webhook_url' => route('github-webhook', $repository),
                'webhook_secret' => (string) $repository->webhook_secret,
            ])
            ->all());
    }
}
