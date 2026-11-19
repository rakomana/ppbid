# ppbid

A simple auction app built with Laravel 12, Livewire, Flux and MySQL. Amounts are in South African Rand.

- There is no public sign-up. Admins add users (and set their passwords) at `/admin/users`.
- Users log in, browse listings and bid. Other users only ever see bidder numbers (e.g. "Bidder #014").
- Each listing is sold as one lot: a bid is the price for the full quantity.
- A bid must be at least the minimum price, and beat the current top bid by at least the increment (R10 by default, `AUCTION_MIN_INCREMENT`).
- Admins create, edit and close listings at `/admin/listings`. Admins can't bid.
- Listing pages refresh every 5 seconds.

## Local setup (Sail)

```bash
composer install
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
npm install && npm run build
```

The app runs at http://localhost:8000 (MySQL on host port 3307). Seeded accounts all use the password `password`:

- `admin@test.com` (admin)
- `alice@example.com`, `bob@example.com`, `carol@example.com` (bidders)
- 12 more fake bidders

The seeder also creates 15 fake listings with bid histories: open ones (one ending in 10 minutes, some with no bids yet) and closed ones (with winners, and one with no bids). Alice is set up to be winning, outbid, a winner and a loser, so every status shows on "My bids". Re-seed at any time with:

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

Run the tests with `./vendor/bin/sail test`.

## Closing expired auctions

Listings past their end time stop accepting bids immediately. The `auctions:close` command then marks them closed and records the winner (the highest bid; on a tie, the earliest):

```bash
php artisan auctions:close
```

It is scheduled to run every minute in `routes/console.php`. For the schedule to run, add this cron entry on the server:

```
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

During local development you can run the scheduler in the foreground instead:

```bash
./vendor/bin/sail artisan schedule:work
```

## Mobile app (installable web app)

ppbid is an installable web app (PWA). On a phone it can be added to the home screen and opens full-screen with the ppbid icon, using the same live server as the website, so bids stay in sync for everyone.

- **Android (Chrome):** an "Install app" button appears on the dashboard and listings pages, or use the browser menu → *Install app*.
- **iPhone/iPad (Safari):** tap *Share* → *Add to Home Screen* (the dashboard shows these steps).

What's included: `public/manifest.webmanifest` (name, colours, icons, shortcuts to Listings and My bids), icons in `public/icons/` plus `public/apple-touch-icon.png`, and a service worker (`public/sw.js`). Pages and live updates always come from the network, so nobody sees stale bids; only static files are cached, and `public/offline.html` is shown when there's no connection.

Phones only install apps from **HTTPS** sites (`localhost` is the one exception), so the app must be deployed to a public HTTPS URL before people can install it. After changing `sw.js`, bump its `VERSION` so phones pick up the update.
