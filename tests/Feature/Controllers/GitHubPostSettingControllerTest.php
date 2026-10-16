<?php

declare(strict_types=1);

use App\Models\User;

it('saves the approval preference and shows it in settings', function (bool $requiresApproval): void {
    $user = User::factory()->create(['github_posts_require_approval' => ! $requiresApproval]);

    $this->actingAs($user)
        ->fromRoute('github-settings.show')
        ->put(route('github-post-setting.update'), ['requires_approval' => $requiresApproval])
        ->assertRedirectToRoute('github-settings.show');

    expect($user->refresh()->github_posts_require_approval)->toBe($requiresApproval);
    $this->get(route('github-settings.show'))
        ->assertInertia(fn ($page) => $page->where('requiresApproval', $requiresApproval));
})->with(['require review' => true, 'publish automatically' => false]);

it('leaves the approval preference unchanged when the value is missing or invalid', function (array $data): void {
    $user = User::factory()->create(['github_posts_require_approval' => true]);

    $this->actingAs($user)
        ->fromRoute('github-settings.show')
        ->put(route('github-post-setting.update'), $data)
        ->assertRedirectToRoute('github-settings.show')
        ->assertSessionHasErrors('requires_approval');

    expect($user->refresh()->github_posts_require_approval)->toBeTrue();
})->with([
    'missing preference' => [[]],
    'invalid preference' => [['requires_approval' => 'sometimes']],
]);

it('requires authentication to change the approval preference', function (): void {
    $this->put(route('github-post-setting.update'), ['requires_approval' => false])
        ->assertRedirectToRoute('home');
});
