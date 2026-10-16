<?php

declare(strict_types=1);

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;

beforeEach(function (): void {
    config(['app.admins' => ['admin']]);
});

it('may approve a commit', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $commit = Commit::factory()->pending()->create();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->post(route('commit-approval.store', $commit));

    $response->assertRedirectToRoute('moderation.show');

    expect($commit->refresh()->status)->toBe(CommitStatus::Approved);
});

it('may not approve a deleted commit', function (): void {
    $admin = User::factory()->create(['username' => 'admin']);
    $commit = Commit::factory()->pending()->create();
    $commit->delete();

    $response = $this->actingAs($admin)
        ->fromRoute('moderation.show')
        ->post(route('commit-approval.store', $commit));

    $response->assertNotFound();

    expect(Commit::withTrashed()->findOrFail($commit->id)->status)->toBe(CommitStatus::Pending);
});

it('may not approve a commit as a non admin', function (): void {
    $commit = Commit::factory()->pending()->create();

    $response = $this->actingAs($commit->user)
        ->post(route('commit-approval.store', $commit));

    $response->assertForbidden();

    expect($commit->refresh()->status)->toBe(CommitStatus::Pending);
});

it('lets an author edit and approve their pending GitHub post', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->for($user)->pending()->create([
        'body' => 'Original post',
        'source_url' => 'https://github.com/example/project/compare/a...b',
    ]);

    $response = $this->actingAs($user)
        ->post(route('commit-approval.store', $commit), ['body' => 'Edited post']);

    $response->assertRedirect();

    expect($commit->refresh()->status)->toBe(CommitStatus::Approved)
        ->and($commit->body)->toBe('Edited post');
});
