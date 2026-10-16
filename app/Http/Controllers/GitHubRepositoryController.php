<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\CreateGitHubRepository;
use App\Actions\DeleteGitHubRepository;
use App\Actions\UpdateGitHubRepository;
use App\Enums\CommitStatus;
use App\Http\Requests\CreateGitHubRepositoryRequest;
use App\Http\Requests\DeleteGitHubRepositoryRequest;
use App\Http\Requests\ShowGitHubSettingsRequest;
use App\Http\Requests\UpdateGitHubRepositoryRequest;
use App\Models\Commit;
use App\Models\GitHubRepository;
use App\Models\User;
use App\Queries\GitHubRepositoriesQuery;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

final readonly class GitHubRepositoryController
{
    public function show(ShowGitHubSettingsRequest $request, #[CurrentUser] User $user, GitHubRepositoriesQuery $query): Response
    {
        $data = $query->handle($user);

        return Inertia::render('github/settings', [
            'repositories' => $data,
            'pendingGitHubPosts' => Commit::query()
                ->where('user_id', $user->id)
                ->where('status', CommitStatus::Pending)
                ->whereNotNull('source_url')
                ->with('user')
                ->oldest()
                ->get(),
            'requiresApproval' => $user->github_posts_require_approval,
        ]);
    }

    public function store(CreateGitHubRepositoryRequest $request, #[CurrentUser] User $user, CreateGitHubRepository $action): RedirectResponse
    {
        $action->handle($user, $request->fullName(), $request->branches());

        return back();
    }

    public function update(UpdateGitHubRepositoryRequest $request, GitHubRepository $repository, UpdateGitHubRepository $action): RedirectResponse
    {
        $action->handle($repository, $request->branches());

        return back();
    }

    public function destroy(DeleteGitHubRepositoryRequest $request, GitHubRepository $repository, DeleteGitHubRepository $action): RedirectResponse
    {
        $action->handle($repository);

        return back();
    }
}
