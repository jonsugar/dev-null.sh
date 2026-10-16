<?php

declare(strict_types=1);

use App\Jobs\ProcessGitHubPush;
use App\Models\Commit;
use App\Models\GitHubRepository;
use App\Models\GitHubWebhookDelivery;
use App\Models\User;

it('removes a watched repository and its deliveries while preserving existing posts', function (): void {
    $user = User::factory()->create();
    $repository = GitHubRepository::factory()->for($user)->create();
    $delivery = GitHubWebhookDelivery::factory()->for($repository, 'repository')->create();
    $commit = Commit::factory()->for($user)->pending()->create([
        'source_url' => 'https://github.com/example/project/compare/before...after',
        'github_delivery_id' => $delivery->delivery_id,
    ]);

    $this->actingAs($user)
        ->fromRoute('github-settings.show')
        ->delete(route('github-repository.destroy', $repository))
        ->assertRedirectToRoute('github-settings.show');

    $this->assertModelMissing($repository);
    $this->assertModelMissing($delivery);
    $this->assertModelExists($commit);

    dispatch_sync(new ProcessGitHubPush($delivery->delivery_id));

    expect(Commit::query()->sole()->id)->toBe($commit->id);
});

it('returns 404 when removing another users watched repository', function (): void {
    $user = User::factory()->create();
    $repository = GitHubRepository::factory()->create();
    $delivery = GitHubWebhookDelivery::factory()->for($repository, 'repository')->create();

    $this->actingAs($user)->delete(route('github-repository.destroy', $repository))
        ->assertNotFound();

    $this->assertModelExists($repository);
    $this->assertModelExists($delivery);
});

it('requires authentication to remove a watched repository', function (): void {
    $repository = GitHubRepository::factory()->create();

    $this->delete(route('github-repository.destroy', $repository))
        ->assertRedirectToRoute('home');

    $this->assertModelExists($repository);
});
