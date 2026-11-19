<?php

/*
|--------------------------------------------------------------------------
| Installable app (PWA)
|--------------------------------------------------------------------------
*/

it('serves a valid web app manifest with installable icons', function () {
    $manifest = json_decode(file_get_contents(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest)
        ->name->toBe('ppbid')
        ->display->toBe('standalone')
        ->start_url->toStartWith('/')
        ->theme_color->toBe('#ea580c');

    $sizes = collect($manifest['icons'])->pluck('sizes');
    expect($sizes)->toContain('192x192', '512x512')
        ->and(collect($manifest['icons'])->pluck('purpose'))->toContain('maskable');

    foreach ($manifest['icons'] as $icon) {
        expect(file_exists(public_path(ltrim($icon['src'], '/'))))->toBeTrue("Missing icon {$icon['src']}");
    }
});

it('ships the service worker, offline page and apple touch icon', function () {
    expect(public_path('sw.js'))->toBeFile()
        ->and(public_path('offline.html'))->toBeFile()
        ->and(public_path('apple-touch-icon.png'))->toBeFile()
        ->and(getimagesize(public_path('apple-touch-icon.png'))[0])->toBe(180)
        ->and(getimagesize(public_path('icons/icon-512.png'))[0])->toBe(512);
});

it('never caches live pages or non-GET requests in the service worker', function () {
    $sw = file_get_contents(public_path('sw.js'));

    expect($sw)
        ->toContain("request.method !== 'GET'")
        ->toContain("request.mode === 'navigate'")
        ->toContain('fetch(request).catch(() => caches.match(OFFLINE_URL))');
});

it('links the manifest and app icons on every page, including login', function (string $uri, bool $signedIn) {
    if ($signedIn) {
        $this->actingAs(bidder());
    }

    $this->get($uri)
        ->assertOk()
        ->assertSee('<link rel="manifest" href="/manifest.webmanifest" />', false)
        ->assertSee('<link rel="apple-touch-icon" href="/apple-touch-icon.png" />', false)
        ->assertSee('<meta name="apple-mobile-web-app-title" content="ppbid" />', false);
})->with([
    'login' => ['/login', false],
    'listings' => ['/listings', true],
    'dashboard' => ['/dashboard', true],
]);

it('shows the install card on the dashboard and listings', function () {
    $this->actingAs(bidder());

    $this->get('/dashboard')->assertSee('data-install-app', false)->assertSee('Get the ppbid app');
    $this->get('/listings')->assertSee('data-install-app', false);
});
