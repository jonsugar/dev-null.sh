<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\UpdateCommitStatus;
use App\Enums\CommitStatus;
use App\Http\Requests\CreateCommitApprovalRequest;
use App\Models\Commit;
use Illuminate\Http\RedirectResponse;

final readonly class CommitApprovalController
{
    public function store(CreateCommitApprovalRequest $request, Commit $commit, UpdateCommitStatus $action): RedirectResponse
    {
        /** @var string|null $body */
        $body = $request->validated('body');

        $action->handle($commit, CommitStatus::Approved, body: $body);

        return back();
    }
}
