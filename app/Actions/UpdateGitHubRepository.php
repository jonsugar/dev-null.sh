<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\GitHubRepository;

final readonly class UpdateGitHubRepository
{
    /** @param list<string> $branches */
    public function handle(GitHubRepository $repository, array $branches): void
    {
        $repository->update(['branches' => $branches]);
    }
}
