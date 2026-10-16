<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateUserGitHubPostApproval;
use App\Http\Requests\UpdateUserGitHubPostApprovalRequest;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final readonly class GitHubPostSettingController
{
    public function update(UpdateUserGitHubPostApprovalRequest $request, #[CurrentUser] User $user, UpdateUserGitHubPostApproval $action): RedirectResponse
    {
        $action->handle($user, $request->boolean('requires_approval'));

        return back();
    }
}
