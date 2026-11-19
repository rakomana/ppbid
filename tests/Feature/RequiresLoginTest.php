<?php

use App\Models\Listing;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;

/**
 * Pages a guest may open: logging in and resetting a password only.
 */
const GUEST_PAGES = ['login', 'forgot-password', 'reset-password/{token}'];

/**
 * Every GET route that renders an app page (skips asset, health and Livewire internals).
 *
 * @return array<int, RouteDefinition>
 */
function pageRoutes(): array
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RouteDefinition $route) => in_array('GET', $route->methods()))
        ->filter(fn (RouteDefinition $route) => in_array('web', $route->gatherMiddleware()))
        ->reject(fn (RouteDefinition $route) => str_starts_with($route->uri(), 'livewire/'))
        ->values()
        ->all();
}

it('protects every page with the auth middleware, except the guest login pages', function () {
    $unprotected = collect(pageRoutes())
        ->reject(fn (RouteDefinition $route) => in_array($route->uri(), GUEST_PAGES))
        ->reject(fn (RouteDefinition $route) => in_array(Authenticate::class, $route->gatherMiddleware())
            || in_array('auth', $route->gatherMiddleware()))
        ->map->uri()
        ->values()
        ->all();

    expect($unprotected)->toBe([]);
});

it('redirects guests to the login page from every protected page', function () {
    $listing = Listing::factory()->create();

    $uris = collect(pageRoutes())
        ->reject(fn (RouteDefinition $route) => in_array($route->uri(), GUEST_PAGES))
        ->map(fn (RouteDefinition $route) => '/'.ltrim(strtr($route->uri(), [
            '{listing}' => $listing->id,
            '{id}' => '1',
            '{hash}' => 'hash',
        ]), '/'));

    expect($uris)->toContain('/', '/listings', '/my-bids', '/dashboard', '/admin/listings');

    foreach ($uris as $uri) {
        expect($this->get($uri)->headers->get('Location'))
            ->toBe(route('login'), "Guest was not redirected to login from {$uri}");
    }
});

it('sends signed-in users from / to the listings', function () {
    $this->actingAs(bidder())->get('/')->assertRedirect('/listings');
});
