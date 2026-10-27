<?php

declare(strict_types=1);

use App\Models\User;

it('renders the browser size before first paint and shares it with settings', function (?string $cookie, int|float $scale, string $factor): void {
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
    'x-small' => ['90', 90, '0.9'],
    'small' => ['95', 95, '0.95'],
    'default' => ['100', 100, '1'],
    'large' => ['105', 105, '1.05'],
    'x-large' => ['110', 110, '1.1'],
    'xx-large' => ['117.5', 117.5, '1.175'],
    'xxx-large' => ['125', 125, '1.25'],
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
