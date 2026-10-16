<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateGitHubCommitBody;
use App\Http\Requests\UpdateGitHubCommitBodyRequest;
use App\Models\Commit;
use Illuminate\Http\RedirectResponse;

final readonly class GitHubPostController
{
    public function update(UpdateGitHubCommitBodyRequest $request, Commit $commit, UpdateGitHubCommitBody $action): RedirectResponse
    {
        $action->handle($commit, $request->string('body')->value());

        return back();
    }
}
