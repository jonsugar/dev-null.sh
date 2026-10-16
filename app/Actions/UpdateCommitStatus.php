<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Models\Commit;
use Illuminate\Support\Facades\DB;

final readonly class UpdateCommitStatus
{
    public function __construct(private UpdateCommitStatuses $updateCommitStatuses)
    {
        //
    }

    public function handle(Commit $commit, CommitStatus $status, ?string $reason = null, ?string $body = null): void
    {
        DB::transaction(function () use ($commit, $status, $reason, $body): void {
            if ($body !== null && $commit->source_url !== null && $commit->status === CommitStatus::Pending) {
                $commit->forceFill(['body' => $body])->save();
            }

            $this->updateCommitStatuses->handle([$commit->id], $status, $reason);
        });
    }
}
