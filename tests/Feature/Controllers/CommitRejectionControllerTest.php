<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('may reject a commit', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $commit = Commit::factory()->pending()->create();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->post(route('commit-rejection.store', $commit));

    $response->assertRedirectToRoute('moderation.show');

    expect($commit->refresh()->status)->toBe(CommitStatus::Rejected);
});

it('may not reject a commit as a non admin', function (): void {
    $commit = Commit::factory()->pending()->create();

    $response = $this->actingAs($commit->user)
        ->post(route('commit-rejection.store', $commit));

    $response->assertForbidden();

    expect($commit->refresh()->status)->toBe(CommitStatus::Pending);
});

it('lets the author edit and reject a pending GitHub post', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->pending()->create([
        'source_url' => 'https://github.com/example/project/compare/before...after',
    ]);

    $this->actingAs($user)
        ->fromRoute('github-settings.show')
        ->post(route('commit-rejection.store', $commit), ['body' => 'Edited private post'])
        ->assertRedirectToRoute('github-settings.show');

    expect($commit->refresh()->status)->toBe(CommitStatus::Rejected)
        ->and($commit->body)->toBe('Edited private post');
});

it('returns 404 when rejecting a GitHub post outside the authors pending queue', function (CommitStatus $status, bool $isAuthor): void {
    $author = User::factory()->create();
    $viewer = $isAuthor ? $author : User::factory()->create();
    $commit = Commit::factory()->for($author)->create([
        'status' => $status,
        'source_url' => 'https://github.com/example/project/compare/before...after',
    ]);
    $body = $commit->body;

    $this->actingAs($viewer)->post(route('commit-rejection.store', $commit), ['body' => 'Unauthorized edit'])
        ->assertNotFound();

    expect($commit->refresh()->status)->toBe($status)
        ->and($commit->body)->toBe($body);
})->with([
    'another authors pending post' => [CommitStatus::Pending, false],
    'an already published post' => [CommitStatus::Approved, true],
    'an already rejected post' => [CommitStatus::Rejected, true],
]);
