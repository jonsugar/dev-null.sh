<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\GitHubRepository;

final readonly class DeleteGitHubRepository
{
    public function handle(GitHubRepository $repository): void
    {
        $repository->delete();
    }
}
