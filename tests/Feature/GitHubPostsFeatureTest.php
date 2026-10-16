<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Jobs\ProcessGitHubPush;
use App\Models\Commit;
use App\Models\GitHubRepository;
use App\Models\GitHubWebhookDelivery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

function githubPushPayload(User $user, GitHubRepository $repository, string $branch = 'main'): array
{
    return [
        'after' => str_repeat('a', 40),
        'before' => str_repeat('b', 40),
        'commits' => [
            ['message' => 'Add the first feature'],
            ['message' => 'Fix the edge case'],
        ],
        'deleted' => false,
        'ref' => 'refs/heads/'.$branch,
        'repository' => [
            'id' => $repository->github_repository_id,
            'full_name' => $repository->full_name,
        ],
        'sender' => ['id' => (int) $user->github_id],
    ];
}

function githubWebhookCall(
    TestCase $test,
    GitHubRepository $repository,
    array $payload,
    string $deliveryId,
    ?string $secret = null,
    string $event = 'push',
): TestResponse {
    $raw = json_encode($payload, JSON_THROW_ON_ERROR);
    $signature = 'sha256='.hash_hmac('sha256', $raw, $secret ?? (string) $repository->webhook_secret);

    return $test->call('POST', route('github-webhook', $repository, false), server: [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_GITHUB_DELIVERY' => $deliveryId,
        'HTTP_X_GITHUB_EVENT' => $event,
        'HTTP_X_HUB_SIGNATURE_256' => $signature,
    ], content: $raw);
}

function watchedRepository(User $user): GitHubRepository
{
    return GitHubRepository::factory()->for($user)->create([
        'github_repository_id' => 42_001,
        'full_name' => 'example/project',
        'branches' => ['main', 'release'],
    ]);
}

function createPushDelivery(User $user, GitHubRepository $repository, string $branch = 'main'): GitHubWebhookDelivery
{
    return GitHubWebhookDelivery::query()->create([
        'delivery_id' => (string) Str::uuid(),
        'event' => 'push',
        'payload' => githubPushPayload($user, $repository, $branch),
        'github_repository_watch_id' => $repository->id,
    ]);
}

it('accepts signed repository webhooks once and queues their payload', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    $repository = watchedRepository($user);
    $deliveryId = (string) Str::uuid();
    $payload = githubPushPayload($user, $repository);

    githubWebhookCall($this, $repository, $payload, $deliveryId)->assertAccepted();
    githubWebhookCall($this, $repository, $payload, $deliveryId)->assertAccepted();

    expect(GitHubWebhookDelivery::query()->count())->toBe(1);
    Queue::assertPushed(ProcessGitHubPush::class, 1);
});

it('rejects a repository webhook with an invalid signature', function (): void {
    $user = User::factory()->create();
    $repository = watchedRepository($user);

    githubWebhookCall(
        $this,
        $repository,
        githubPushPayload($user, $repository),
        (string) Str::uuid(),
        'incorrect-secret',
    )->assertUnauthorized();

    expect(GitHubWebhookDelivery::query()->count())->toBe(0);
});

it('creates one pending editable post for a watched push by the connected user', function (): void {
    $user = User::factory()->create(['github_posts_require_approval' => true]);
    $repository = watchedRepository($user);
    $delivery = createPushDelivery($user, $repository);

    dispatch_sync(new ProcessGitHubPush($delivery->delivery_id));
    dispatch_sync(new ProcessGitHubPush($delivery->delivery_id));

    $commit = Commit::query()->sole();
    expect($commit->status)->toBe(CommitStatus::Pending)
        ->and($commit->body)->toContain('Add the first feature')
        ->and($commit->body)->toContain('/compare/')
        ->and($commit->source_url)->toContain('/compare/')
        ->and($delivery->refresh()->processed_at)->not->toBeNull();
});

it('publishes immediately when the user turns off approval', function (): void {
    $user = User::factory()->create(['github_posts_require_approval' => false]);
    $repository = watchedRepository($user);
    $delivery = createPushDelivery($user, $repository);

    dispatch_sync(new ProcessGitHubPush($delivery->delivery_id));

    expect(Commit::query()->sole()->status)->toBe(CommitStatus::Approved);
});

it('ignores pushes from another pusher and from unselected branches', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $repository = watchedRepository($user);
    $otherPusherDelivery = createPushDelivery($other, $repository);
    $otherBranchDelivery = createPushDelivery($user, $repository, 'unwatched');

    dispatch_sync(new ProcessGitHubPush($otherPusherDelivery->delivery_id));
    dispatch_sync(new ProcessGitHubPush($otherBranchDelivery->delivery_id));

    expect(Commit::query()->count())->toBe(0)
        ->and($otherPusherDelivery->refresh()->processed_at)->not->toBeNull()
        ->and($otherBranchDelivery->refresh()->processed_at)->not->toBeNull();
});

it('accepts signed ping events without queueing a push', function (): void {
    Queue::fake();
    $user = User::factory()->create();
    $repository = watchedRepository($user);

    githubWebhookCall($this, $repository, ['zen' => 'test', 'repository' => ['full_name' => $repository->full_name]], (string) Str::uuid(), event: 'ping')
        ->assertAccepted();

    Queue::assertNothingPushed();
});

it('lets users add repositories and returns a unique encrypted webhook secret', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('github-repository.store'), [
        'full_name' => 'Example/Project',
        'branches' => ['main', 'release'],
    ])->assertRedirect();

    $repository = GitHubRepository::query()->sole();
    expect($repository->full_name)->toBe('example/project')
        ->and($repository->branches)->toBe(['main', 'release'])
        ->and(mb_strlen((string) $repository->webhook_secret))->toBe(64)
        ->and(DB::table('github_repositories')->where('id', $repository->id)->value('webhook_secret'))
        ->not->toBe($repository->webhook_secret);
});

it('rejects an invalid repository name or empty branch list', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('github-repository.store'), [
        'full_name' => 'not-a-repository-name',
        'branches' => [],
    ])->assertSessionHasErrors(['full_name', 'branches']);

    expect(GitHubRepository::query()->count())->toBe(0);
});

it('updates branches only for the repository owner', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $repository = watchedRepository($user);

    $this->actingAs($user)->put(route('github-repository.update', $repository), [
        'branches' => ['main', 'release/next'],
    ])->assertRedirect();
    expect($repository->refresh()->branches)->toBe(['main', 'release/next']);

    $this->actingAs($other)->put(route('github-repository.update', $repository), [
        'branches' => ['main'],
    ])->assertNotFound();
});

it('shows the same pending posts on GitHub settings and notifications', function (): void {
    $user = User::factory()->create();
    $repository = watchedRepository($user);
    $commit = Commit::factory()->for($user)->pending()->create(['source_url' => 'https://github.com/example/project/compare/a...b']);

    $this->actingAs($user)->get(route('github-settings.show'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('github/settings')
            ->where('pendingGitHubPosts.0.id', $commit->id)
            ->where('requiresApproval', true)
            ->where('repositories.0.id', $repository->id)
            ->where('repositories.0.webhook_secret', $repository->webhook_secret));

    $this->actingAs($user)->get(route('notification.index'))
        ->assertInertia(fn ($page) => $page
            ->component('notification/index')
            ->where('pendingGitHubPosts.0.id', $commit->id));
});

it('lets the author update a pending GitHub post but not someone elses', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $commit = Commit::factory()->for($user)->pending()->create(['source_url' => 'https://github.com/example/project/compare/a...b']);

    $this->actingAs($user)->put(route('github-post.update', $commit), ['body' => 'Edited before review'])
        ->assertRedirect();
    expect($commit->refresh()->body)->toBe('Edited before review');

    $this->actingAs($other)->put(route('github-post.update', $commit), ['body' => 'A different post'])
        ->assertNotFound();
});
