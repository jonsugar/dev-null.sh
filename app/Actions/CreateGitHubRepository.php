<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\GitHubRepository;
use App\Models\User;
use Illuminate\Support\Str;

final readonly class CreateGitHubRepository
{
    /** @param list<string> $branches */
    public function handle(User $user, string $fullName, array $branches): GitHubRepository
    {
        return $user->githubRepositories()->create([
            'full_name' => mb_strtolower($fullName),
            'branches' => $branches,
            'webhook_secret' => Str::random(64),
        ]);
    }
}
