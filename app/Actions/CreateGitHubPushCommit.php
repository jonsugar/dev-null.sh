<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\GitHubWebhookDelivery;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final readonly class CreateGitHubPushCommit
{
    public function handle(User $user, GitHubWebhookDelivery $delivery, string $body, string $sourceUrl): Commit
    {
        return DB::transaction(function () use ($user, $delivery, $body, $sourceUrl): Commit {
            $existing = Commit::query()->where('github_delivery_id', $delivery->delivery_id)->first();

            if ($existing instanceof Commit) {
                return $existing;
            }

            return $user->commits()->create([
                'body' => $body,
                'status' => $user->github_posts_require_approval ? CommitStatus::Pending : CommitStatus::Approved,
                'source_url' => $sourceUrl,
                'github_delivery_id' => $delivery->delivery_id,
            ]);
        });
    }
}
