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
        'HTTP_ACCEPT' => 'application/json',
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
    return GitHubWebhookDelivery::factory()->for($repository, 'repository')->create([
        'payload' => githubPushPayload($user, $repository, $branch),
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

it('returns 401 for webhooks missing an authentication header', function (string $header): void {
    Queue::fake(ProcessGitHubPush::class);
    $repository = watchedRepository(User::factory()->create());
    $raw = json_encode(['zen' => 'test'], JSON_THROW_ON_ERROR);
    $server = [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_GITHUB_DELIVERY' => (string) Str::uuid(),
        'HTTP_X_GITHUB_EVENT' => 'ping',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $raw, (string) $repository->webhook_secret),
    ];
    unset($server[$header]);

    $this->call('POST', route('github-webhook', $repository, false), server: $server, content: $raw)
        ->assertUnauthorized();

    expect(GitHubWebhookDelivery::query()->count())->toBe(0);
    Queue::assertNothingPushed();
})->with([
    'signature' => 'HTTP_X_HUB_SIGNATURE_256',
    'delivery identifier' => 'HTTP_X_GITHUB_DELIVERY',
    'event name' => 'HTTP_X_GITHUB_EVENT',
]);

it('returns 401 for a webhook with an invalid delivery identifier', function (): void {
    Queue::fake(ProcessGitHubPush::class);
    $user = User::factory()->create();
    $repository = watchedRepository($user);

    githubWebhookCall($this, $repository, githubPushPayload($user, $repository), 'invalid-delivery')
        ->assertUnauthorized();

    expect(GitHubWebhookDelivery::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('returns 422 for a signed push without the required payload fields', function (): void {
    Queue::fake(ProcessGitHubPush::class);
    $repository = watchedRepository(User::factory()->create());

    githubWebhookCall($this, $repository, [], (string) Str::uuid())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['repository.id', 'repository.full_name', 'sender.id', 'ref']);

    expect(GitHubWebhookDelivery::query()->count())->toBe(0);
    Queue::assertNothingPushed();
});

it('marks malformed or unrelated stored pushes as processed without creating a post', function (string $field, mixed $value): void {
    $user = User::factory()->create();
    $repository = watchedRepository($user);
    $delivery = createPushDelivery($user, $repository);
    $payload = $delivery->payload;
    data_set($payload, $field, $value);
    $delivery->update(['payload' => $payload]);

    dispatch_sync(new ProcessGitHubPush($delivery->delivery_id));

    expect(Commit::query()->count())->toBe(0)
        ->and($delivery->refresh()->processed_at)->not->toBeNull();
})->with([
    'invalid sender identifier' => ['sender.id', 'invalid'],
    'invalid repository identifier' => ['repository.id', null],
    'invalid repository name' => ['repository.full_name', []],
    'invalid branch reference' => ['ref', null],
    'unknown GitHub account' => ['sender.id', 2_147_483_647],
    'another repository identifier' => ['repository.id', 42_002],
    'another repository name' => ['repository.full_name', 'example/another-project'],
    'deleted branch' => ['deleted', true],
]);

it('does not create a post when a stored push has no usable commit messages', function (mixed $commits): void {
    $user = User::factory()->create();
    $repository = watchedRepository($user);
    $delivery = createPushDelivery($user, $repository);
    $payload = $delivery->payload;
    $payload['commits'] = $commits;
    $delivery->update(['payload' => $payload]);

    dispatch_sync(new ProcessGitHubPush($delivery->delivery_id));

    expect(Commit::query()->count())->toBe(0)
        ->and($delivery->refresh()->processed_at)->not->toBeNull();
})->with([
    'empty commits' => [[]],
    'invalid collection' => ['invalid'],
    'missing or blank messages' => [[null, ['message' => null], ['message' => " \n "]]],
]);

it('summarizes usable messages while skipping malformed commit entries', function (): void {
    $user = User::factory()->create();
    $repository = watchedRepository($user);
    $delivery = createPushDelivery($user, $repository);
    $payload = $delivery->payload;
    $payload['commits'] = [
        ['message' => ' Build   feature '],
        ['message' => false],
        'invalid entry',
        [],
        ['message' => '   '],
        ['message' => '0'],
    ];
    $delivery->update(['payload' => $payload]);

    dispatch_sync(new ProcessGitHubPush($delivery->delivery_id));

    expect(Commit::query()->sole()->body)
        ->toContain('Pushed 2 commits to example/project: Build feature · 0');
});

it('links to the repository when a push has no comparison references', function (): void {
    $user = User::factory()->create();
    $repository = watchedRepository($user);
    $delivery = createPushDelivery($user, $repository);
    $payload = $delivery->payload;
    unset($payload['before'], $payload['after']);
    $delivery->update(['payload' => $payload]);

    dispatch_sync(new ProcessGitHubPush($delivery->delivery_id));

    $commit = Commit::query()->sole();
    expect($commit->source_url)->toBe('https://github.com/example/project')
        ->and($commit->body)->toContain("\nhttps://github.com/example/project");
});

it('records the repository identifier on the first push and limits a long summary', function (): void {
    $user = User::factory()->create();
    $repository = watchedRepository($user);
    $repository->update(['github_repository_id' => null]);

    $delivery = createPushDelivery($user, $repository);
    $payload = $delivery->payload;
    $payload['repository'] = ['id' => 42_001, 'full_name' => 'Example/Project'];
    $payload['commits'] = [['message' => str_repeat('Release changes ', 100)]];
    $delivery->update(['payload' => $payload]);

    dispatch_sync(new ProcessGitHubPush($delivery->delivery_id));

    $commit = Commit::query()->sole();
    expect($repository->refresh()->github_repository_id)->toBe(42_001)
        ->and(mb_strlen($commit->body))->toBeLessThanOrEqual(280)
        ->and($commit->body)->toContain('…', '/compare/');
});

it('preserves an existing post when a delivery is retried before being marked processed', function (): void {
    $user = User::factory()->create();
    $repository = watchedRepository($user);
    $delivery = createPushDelivery($user, $repository);
    $commit = Commit::factory()->for($user)->pending()->create([
        'github_delivery_id' => $delivery->delivery_id,
        'source_url' => 'https://github.com/example/project',
        'body' => 'An edited post that must survive a retry',
    ]);

    dispatch_sync(new ProcessGitHubPush($delivery->delivery_id));

    $saved = Commit::query()->sole();
    expect($saved->id)->toBe($commit->id)
        ->and($saved->body)->toBe('An edited post that must survive a retry')
        ->and($delivery->refresh()->processed_at)->not->toBeNull();
});
