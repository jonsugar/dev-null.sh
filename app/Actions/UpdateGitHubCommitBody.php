<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;

final readonly class UpdateGitHubCommitBody
{
    public function handle(Commit $commit, string $body): void
    {
        $commit->forceFill(['body' => $body])->save();
    }
}
