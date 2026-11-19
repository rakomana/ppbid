# ppbid documentation

ppbid is a simple live auction web app. Admins put up listings; logged-in users bid on them; the highest bid when a listing ends wins.

Built with Laravel 12, Livewire 3 (with Flux UI), Tailwind and MySQL. Amounts are in South African Rand and shown as `R1,250.00`. Times are South African time (`Africa/Johannesburg`).

---

## 1. Getting started

### Requirements

- Docker (the app runs in [Laravel Sail](https://laravel.com/docs/sail))
- PHP 8.2+ and Composer, Node.js and npm (for installing dependencies and building assets)

### Install and run

```bash
composer install
cp .env.example .env
./vendor/bin/sail up -d
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
npm install && npm run build
```

Open **http://localhost:8000**. MySQL is available on host port **3307** (database `auction`, user `sail`, password `password`).

After changing views or CSS, run `npm run build` again (or keep `npm run dev` running).

### Seeded accounts

Every seeded account uses the password **`password`**.

| Email | Role |
|---|---|
| `admin@test.com` | Admin |
| `alice@example.com`, `bob@example.com`, `carol@example.com` | Bidders |
| 12 more fake users | Bidders |

The seeder also creates 15 listings with bid histories (open, ending soon, closed with and without a winner). Alice is set up to be winning, outbid, a winner and a loser. Reset everything with:

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

> Change these passwords before putting the app anywhere public.

---

## 2. How the auction works

- **One lot per listing.** A listing has a quantity, but it's sold as a single lot: a bid is the price for *all* items.
- **Valid bids.** A bid must be at least the listing's minimum price, and at least **R10** more than the current top bid. The increment is set by `AUCTION_MIN_INCREMENT` in `.env`.
- **Ties.** If two bids are equal, the earlier one wins.
- **No bidding on yourself.** The current highest bidder can't bid again until someone outbids them.
- **Admins can't bid.**
- **Ending.** No bids are accepted once a listing passes its end time. A scheduled job then marks it closed and records the winner.
- **Privacy.** Users only ever see each other as bidder numbers, e.g. user 14 is **Bidder #014**. Names and emails are visible to admins only.

---

## 3. Using the app

### Bidders

| Page | What it's for |
|---|---|
| **Dashboard** | Your bidder number, how many auctions you're winning now, and how many you've won. |
| **Listings** | Open listings (soonest-ending first) and recently closed ones with their winners. |
| **Listing page** | Details, a live countdown, the current leader, all participants, the bid history, and the bid form. Refreshes every 5 seconds. Shows "You've been outbid" or "You won this auction!" when relevant. |
| **My bids** | Everything you've bid on, marked **Winning**, **Outbid**, **Won** or **Lost**. |
| **Settings** | Change your name, email, password or appearance (light/dark). |

There is no public sign-up. New users get their login details from an admin.

### Admins

The **Admin** section of the sidebar has:

- **Manage listings** (`/admin/listings`)
  - Create a listing: title, description (optional), minimum price, quantity and end date/time.
  - Edit a listing. Once it has bids, the minimum price and quantity are locked.
  - **Close now** ends a listing early and records the current top bidder as the winner.
- **Users** (`/admin/users`)
  - **Add user:** set their name, email and password (use **Generate** for a random one), and optionally make them an admin. They can log in straight away.
  - **Edit user:** change details or reset the password (leave it blank to keep the current one).
  - **Deactivate account:** used instead of deleting. The user is signed out and can't log in or bid. Their bids on listings that haven't closed are **withdrawn**, so the next-highest bidder takes the lead; withdrawn bids stay in the history, struck through. Closed auctions and past wins are not changed.
  - **Reactivate account:** lets them log in again. Withdrawn bids stay withdrawn, but they can bid again.
  - An admin can't remove their own admin access or deactivate themselves.

---

## 4. Closing auctions (scheduler)

Listings past their end time stop taking bids immediately. The `auctions:close` command then marks them **closed** and records the winner. It's scheduled to run every minute.

Run it by hand:

```bash
./vendor/bin/sail artisan auctions:close
```

On a server, add this cron entry so the schedule runs:

```
* * * * * cd /path-to-project && php artisan schedule:run >> /dev/null 2>&1
```

Locally, you can run the scheduler in the foreground instead:

```bash
./vendor/bin/sail artisan schedule:work
```

---

## 5. Mobile app

ppbid is an installable web app (PWA). It uses the same live server as the website, so everyone sees the same bids.

- **Android (Chrome):** tap **Install app** on the dashboard or listings page, or use the browser menu → *Install app*.
- **iPhone/iPad (Safari):** tap **Share** → **Add to Home Screen**.

Phones only install apps from **HTTPS** sites, so the app must be deployed to a public HTTPS address first. When offline, the app shows a "You're offline" page instead of stale bids.

---

## 6. For developers

### Where things live

| Path | What it does |
|---|---|
| `app/Actions/PlaceBid.php` | Places a bid. Locks the listing row and checks every bidding rule, so two simultaneous bids can't both get through. |
| `app/Actions/CloseListing.php` | Closes a listing and records the winner. Used by **Close now** and by `auctions:close`. |
| `app/Actions/DeactivateUser.php` | Deactivates a user and withdraws their bids on listings that haven't closed. |
| `app/Console/Commands/CloseExpiredAuctions.php` | The `auctions:close` command. Scheduled in `routes/console.php`. |
| `app/Models/Listing.php` | Listing rules: `isOpen()`, `highestBid()`, `minimumNextBid()`, `timeRemaining()`. `bids()` returns only bids that count; `allBids()` includes withdrawn ones. |
| `app/Models/User.php` | `bidder_number`, `is_admin`, `isDeactivated()`. |
| `app/Livewire/` | One Livewire component per page (listings, listing page, my bids, dashboard, admin pages). Views are in `resources/views/livewire/`. |
| `app/Http/Middleware/EnsureUserIsAdmin.php` | Returns 403 to non-admins on `/admin` (alias `admin`). |
| `app/Http/Middleware/EnsureUserIsActive.php` | Signs out deactivated users on their next request. |
| `app/Support/Money.php` | `Money::format()` for `R1,250.00` formatting. |
| `config/auction.php` | `min_increment` (default R10). |
| `database/seeders/` | Demo users, listings and bids. |
| `public/manifest.webmanifest`, `public/sw.js` | Installable app setup and service worker. |

### Database

- **users**: plus `is_admin` and `deactivated_at`.
- **listings**: `title`, `description`, `min_price`, `quantity`, `ends_at`, `status` (`active`/`closed`), `winner_id`.
- **bids**: `listing_id`, `user_id`, `amount`, `voided_at` (set when a bid is withdrawn).

### Tests

```bash
./vendor/bin/sail test
```

Tests use Pest and PHPUnit and run against a separate `testing` database, so they don't touch your data. Code style is checked with:

```bash
./vendor/bin/pint --test
```

---

## 7. Troubleshooting

| Problem | Fix |
|---|---|
| Page looks unstyled or new styles are missing | Run `npm run build`. |
| Listings past their end time still show "Ended, closing" | The scheduler isn't running. Run `sail artisan auctions:close` or set up the cron entry above. |
| Times look two hours off | `APP_TIMEZONE` in `.env` should be `Africa/Johannesburg`, or left out (that's the default). |
| "This account has been deactivated" at login | An admin needs to reactivate the account under **Users**. |
| Port 8000 or 3307 already in use | Change `APP_PORT` or `FORWARD_DB_PORT` in `.env`, then `sail up -d` again. |
