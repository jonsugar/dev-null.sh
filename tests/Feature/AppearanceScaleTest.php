<?php

declare(strict_types=1);

use App\Models\User;

it('renders the browser size before first paint and shares it with settings', function (?string $cookie, int $scale, string $factor): void {
    $this->withoutVite();

    if ($cookie !== null) {
        $this->withUnencryptedCookie('ui_scale', $cookie);
    }

    $response = $this->actingAs(User::factory()->create())->get(route('appearance.edit'));

    $response->assertOk()
        ->assertSee('--ui-scale: '.$factor.'; zoom: var(--ui-scale)', false)
        ->assertInertia(fn ($page) => $page
            ->component('appearance/update')
            ->where('uiScale', $scale));
})->with([
    'small' => ['90', 90, '0.9'],
    'default' => ['100', 100, '1'],
    'large' => ['110', 110, '1.1'],
    'largest' => ['125', 125, '1.25'],
    'missing' => [null, 100, '1'],
    'unsupported' => ['200', 100, '1'],
    'empty' => ['', 100, '1'],
    'malformed' => ['125; color: red', 100, '1'],
]);

it('keeps the browser size when visiting a public page without signing in', function (): void {
    $this->withoutVite();

    $this->withUnencryptedCookie('ui_scale', '110')->get(route('home'))
        ->assertOk()
        ->assertSee('--ui-scale: 1.1; zoom: var(--ui-scale)', false)
        ->assertInertia(fn ($page) => $page->where('uiScale', 110));
});
