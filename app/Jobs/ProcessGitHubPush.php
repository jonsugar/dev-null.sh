<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\CreateGitHubPushCommit;
use App\Models\GitHubRepository;
use App\Models\GitHubWebhookDelivery;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Str;

#[Backoff([5, 15, 60, 180])]
#[Tries(5)]
final class ProcessGitHubPush implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $deliveryId) {}

    public function handle(CreateGitHubPushCommit $action): void
    {
        $delivery = GitHubWebhookDelivery::query()->find($this->deliveryId);

        if (! $delivery instanceof GitHubWebhookDelivery || $delivery->processed_at !== null) {
            return;
        }

        $payload = $delivery->payload;
        $senderId = data_get($payload, 'sender.id');
        $repositoryId = data_get($payload, 'repository.id');
        $repositoryName = data_get($payload, 'repository.full_name');
        $ref = data_get($payload, 'ref');

        if (! is_numeric($senderId) || ! is_numeric($repositoryId) || ! is_string($repositoryName) || ! is_string($ref)) {
            $delivery->forceFill(['processed_at' => now()])->save();

            return;
        }

        $user = User::query()->where('github_id', (string) $senderId)->first();
        $branch = Str::after($ref, 'refs/heads/');
        $repository = $delivery->repository;

        if (! $repository instanceof GitHubRepository || ! $user instanceof User || $repository->user_id !== $user->id
            || mb_strtolower($repositoryName) !== $repository->full_name
            || ($repository->github_repository_id !== null && $repository->github_repository_id !== (int) $repositoryId)
            || ! in_array($branch, $repository->branches, true)
            || data_get($payload, 'deleted') === true) {
            $delivery->forceFill(['processed_at' => now()])->save();

            return;
        }

        $messages = collect(data_get($payload, 'commits', []))
            ->filter(fn (mixed $commit): bool => is_array($commit) && is_string(data_get($commit, 'message')))
            ->pluck('message')
            ->map(fn (string $message): string => Str::squish($message))
            ->filter()
            ->values();

        if ($messages->isEmpty()) {
            $delivery->forceFill(['processed_at' => now()])->save();

            return;
        }

        $before = data_get($payload, 'before');
        $after = data_get($payload, 'after');
        $sourceUrl = is_string($before) && is_string($after)
            ? "https://github.com/{$repository->full_name}/compare/{$before}...{$after}"
            : "https://github.com/{$repository->full_name}";
        $summary = 'Pushed '.$messages->count().' commits to '.$repository->full_name.': '.$messages->implode(' · ');
        $body = Str::limit($summary, max(3, 280 - mb_strlen($sourceUrl) - 2), '…')."\n{$sourceUrl}";

        if ($repository->github_repository_id === null) {
            $repository->update(['github_repository_id' => (int) $repositoryId]);
        }

        $action->handle($user, $delivery, $body, $sourceUrl);

        $delivery->forceFill(['processed_at' => now()])->save();
    }
}
