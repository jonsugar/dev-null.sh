<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\User;

final readonly class UpdateUserGitHubPostApproval
{
    public function handle(User $user, bool $requiresApproval): void
    {
        $user->forceFill(['github_posts_require_approval' => $requiresApproval])->save();
    }
}
