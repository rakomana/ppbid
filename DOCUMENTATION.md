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

The seeder also creates 16 listings with bid histories (open, ending soon, with a Buy Now price, closed with and without a winner, and one bought with Buy Now). Alice is set up to be winning, outbid, a winner and a loser. Reset everything with:

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

> Change these passwords before putting the app anywhere public.

---

## 2. How the auction works

- **One lot per listing.** A listing has a quantity, but it's sold as a single lot: a bid is the price for *all* items.
- **Valid bids.** A bid must be at least the listing's minimum price, and beat the current top bid by at least the listing's **bid increment**. An admin can set the increment per listing; listings without one use the default of **R10** (`AUCTION_MIN_INCREMENT` in `.env`). The first bid only needs to reach the minimum price. On the listing page, the **−** / **+** buttons next to the bid amount (or the ↑ / ↓ keys) change the amount by the increment, never going below the minimum next bid; you can still type any amount.
- **Ties.** If two bids are equal, the earlier one wins.
- **No bidding on yourself.** The current highest bidder can't bid again until someone outbids them.
- **Admins can't bid.**
- **Buy Now.** A listing can have a Buy Now price. Any bidder can pay it (after confirming) to win the whole lot at once, which ends the auction. Bids at or above the Buy Now price aren't allowed. The current leader can use Buy Now too.
- **Ending.** No bids are accepted once a listing passes its end time. A scheduled job then marks it closed and records the winner.
- **Privacy.** Users only ever see each other as bidder numbers, e.g. user 14 is **Bidder #014**. Names and emails are visible to admins only.

---

## 3. Using the app

### Bidders

| Page | What it's for |
|---|---|
| **Dashboard** | Your bidder number, how many auctions you're winning now, and how many you've won. |
| **Listings** | Open listings (soonest-ending first) and recently closed ones with their winners. |
| **Listing page** | Details, a live countdown, the current leader, all participants, the bid history, and the bid form. If the listing has a Buy Now price, a **Buy now for R…** button that asks you to confirm before buying. Refreshes every 5 seconds. Shows "You've been outbid", "You won this auction!" or "You bought this with Buy Now!" when relevant. |
| **My bids** | Everything you've bid on, marked **Winning**, **Outbid**, **Won** or **Lost**. Won auctions have an **Invoice** button, and a **Return** button while returns are open. |
| **Notifications** | Everything the bell has told you (see section 4). |
| **Settings** | Change your name, email, password or appearance (light/dark). |

There is no public sign-up and no "forgot password" link. New users get their login details from an admin, and an admin resets forgotten passwords under **Users**.

### Admins

The **Admin** section of the sidebar has:

- **Manage listings** (`/admin/listings`)
  - Create a listing: title, description (optional), minimum price, quantity and end date/time. The end time is pre-filled to **12 hours from now**; change it if needed. The default length is set by `AUCTION_DEFAULT_DURATION_HOURS` in `.env`.
  - **Buy Now price** (optional): leave empty for no Buy Now. It must be higher than the minimum price.
  - **Bid increment** (optional): how much each bid must beat the top bid by. Leave empty for the default (R10).
  - Edit a listing. Once it has bids, the minimum price, bid increment and quantity are locked. The Buy Now price can still be added, changed or removed, but must be at least the top bid plus the increment.
  - **Close now** ends a listing early and records the current top bidder as the winner.
- **Returns** (`/admin/returns`): faulty units logged by winners. Open returns are shown first; the sidebar shows how many are open. See **Returns** below.
- **Users** (`/admin/users`)
  - **Add user:** set their name, email and password (use **Generate** for a random one), and optionally make them an admin. They can log in straight away.
  - **Edit user:** change details or reset the password (leave it blank to keep the current one).
  - **Deactivate account:** used instead of deleting. The user is signed out and can't log in or bid. Their bids on listings that haven't closed are **withdrawn**, so the next-highest bidder takes the lead; withdrawn bids stay in the history, struck through. Closed auctions and past wins are not changed.
  - **Reactivate account:** lets them log in again. Withdrawn bids stay withdrawn, but they can bid again.
  - An admin can't remove their own admin access or deactivate themselves.
  - **Last login:** the Users list shows when each user last logged in ("Never" if they haven't yet), and the edit page shows the exact date and time. "Log in as" by an admin doesn't count as the user logging in.
  - **Log in as:** see the app exactly as that bidder does, e.g. to check what they see on a listing or in their notifications. An amber banner shows who you're viewing as; click **Return to admin** to go back. While viewing as someone you **can't bid** or log returns, change their profile or password, or mark their notifications as read, and the admin area is closed. You can't log in as another admin, a deactivated user or yourself. Each start and stop is recorded in the Laravel log.

### Returns

Faulty units are **replaced, not refunded**.

**Buyers.** On the listing page of an auction you won, open **Returns → Log a return** (or **Return** on My bids). Enter how many units are faulty, what's wrong, and any details. You can do this for 7 days after the auction ends (`RETURNS_WINDOW_DAYS`). You can log more than one return per lot, but units already in an open return can't be logged again until it's done. You can cancel a return until an admin has looked at it. You'll get a notification at each step, and once your units are replaced you can download a **replacement note** (PDF).

**Admins.** In **Returns**:

1. **Approve** a requested return, setting how many units you'll replace (you can approve fewer than were logged), with an optional note for the buyer. Or **Reject** it, with a reason the buyer sees.
2. **Received** when the faulty units are back with you.
3. **Replaced** when the working units have gone to the buyer. You can skip **Received** if the swap happens over the counter.

If there's no stock of the same module, replace with a similar or better one and say so in the note. The replacement note (`RN-…`) can be downloaded from the Returns page or the listing page.

### Invoices

When you win an auction, a PDF invoice is ready straight away. Download it from the listing page (**Download invoice**) or from **My bids** (**Invoice**). Admins can download the invoice for any won auction, from the listing page or **Manage listings**.

**Sharing on WhatsApp.** Next to each invoice button there's a **Share** button (an icon on My bids). On a phone it opens the share menu with the PDF attached: choose WhatsApp, pick the chat and send. It works with any app in the share menu, and needs no WhatsApp setup. On browsers that can't share files (most desktops) the PDF is downloaded instead, so you can attach it in WhatsApp yourself. Sharing needs the site to be on HTTPS. Only the winner and admins see the button.

- The invoice number comes from the listing (listing 123 is **INV-000123**). Use it as the payment reference.
- The total is the winning bid for the whole lot. **No VAT** is charged.
- Payment is due 7 days after the auction ends (`INVOICE_PAYMENT_TERMS_DAYS`).
- The seller's name, address and banking details are set in `.env` (`INVOICE_COMPANY_NAME`, `INVOICE_COMPANY_ADDRESS`, `INVOICE_BANK_NAME`, `INVOICE_BANK_ACCOUNT_NAME`, `INVOICE_BANK_ACCOUNT_NUMBER`, `INVOICE_BANK_BRANCH_CODE`, and so on; see `.env.example`).

---

## 4. Notifications

Notifications are **in the app only**: ppbid never sends email.

- The **bell** in the sidebar (top of the screen on phones) shows how many are unread. Open it to see the latest, or go to **Notifications** for the full list.
- A pop-up appears in the corner when a new one arrives. The bell checks every 15 seconds.
- Clicking a notification marks it read and opens the listing. **Mark all as read** clears the count.

| Notification | Sent to | When |
|---|---|---|
| **You've been outbid** | The bidder who was leading | Someone bids higher. If it happens again before you read it, the old one is replaced. |
| **Auction ending soon** | Everyone who bid on it | Once, when 1 hour or less is left (`AUCTION_ENDING_SOON_MINUTES`). If an admin changes the end time, it can be sent again. |
| **You won!** | The winner | The auction closes, or they buy it with Buy Now. Says the invoice is ready on the listing page. |
| **Auction ended** / **Auction closed early** | The other bidders | The auction closes, with the winning amount but never the winner's name. After a Buy Now it says it was bought with Buy Now, by bidder number. |
| **Listing closed** / **Listing closed early** / **Listing bought now** | Admins | The auction closes, with the winner's name and amount. The admin who pressed **Close now** isn't notified. |

Deactivated users get no notifications. "Ending soon" and "won/lost" rely on the scheduler (section 5).

---

## 5. Closing auctions (scheduler)

Listings past their end time stop taking bids immediately. The `auctions:close` command then marks them **closed**, records the winner and sends the won/lost notifications. `auctions:notify-ending-soon` sends the "ending soon" notifications. Both are scheduled to run every minute.

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

## 6. Mobile app

ppbid is an installable web app (PWA). It uses the same live server as the website, so everyone sees the same bids.

- **Android (Chrome):** tap **Install app** on the dashboard or listings page, or use the browser menu → *Install app*.
- **iPhone/iPad (Safari):** tap **Share** → **Add to Home Screen**.

Phones only install apps from **HTTPS** sites, so the app must be deployed to a public HTTPS address first. When offline, the app shows a "You're offline" page instead of stale bids.

---

## 7. For developers

### Where things live

| Path | What it does |
|---|---|
| `app/Actions/PlaceBid.php` | Places a bid. Locks the listing row and checks every bidding rule, so two simultaneous bids can't both get through. |
| `app/Actions/BuyNow.php` | Buys a listing at its Buy Now price: locks the listing, checks the rules (including that the price hasn't changed since the bidder confirmed), saves a bid marked `is_buy_now`, and closes the listing in the same transaction. |
| `app/Actions/CloseListing.php` | Closes a listing and records the winner. Used by **Close now** and by `auctions:close`. |
| `app/Actions/DeactivateUser.php` | Deactivates a user and withdraws their bids on listings that haven't closed. |
| `app/Console/Commands/CloseExpiredAuctions.php` | The `auctions:close` command. Scheduled in `routes/console.php`. |
| `app/Console/Commands/NotifyEndingSoon.php` | The `auctions:notify-ending-soon` command. Scheduled in `routes/console.php`. |
| `app/Notifications/` | One class per notification. All use the `database` channel only (no email). |
| `app/Livewire/NotificationBell.php`, `app/Livewire/Notifications.php` | The bell (count, dropdown, pop-ups) and the Notifications page. |
| `app/Models/Listing.php` | Listing rules: `isOpen()`, `highestBid()`, `bidIncrement()`, `minimumNextBid()`, `buyNowAvailable()`, `wasBoughtNow()`, `timeRemaining()`. `bids()` returns only bids that count; `allBids()` includes withdrawn ones. |
| `app/Models/User.php` | `bidder_number`, `is_admin`, `isDeactivated()`. |
| `app/Livewire/` | One Livewire component per page (listings, listing page, my bids, dashboard, admin pages). Views are in `resources/views/livewire/`. |
| `app/Http/Middleware/EnsureUserIsAdmin.php` | Returns 403 to non-admins on `/admin` (alias `admin`). |
| `app/Http/Middleware/EnsureUserIsActive.php` | Signs out deactivated users on their next request. |
| `app/Support/Impersonation.php`, `app/Http/Controllers/ImpersonationController.php` | "Log in as" for admins: start/stop, the rules for who can be impersonated, and log entries. The admin's id is kept in the session (`impersonator_id`). |
| `app/Listeners/RecordLastLogin.php` | Saves `users.last_login_at` on each real login (not on "Log in as" switches). |
| `app/Http/Middleware/BlockWhileImpersonating.php` | Blocks the profile and password settings (and their Livewire actions) while impersonating. |
| `app/Models/ReturnRequest.php`, `app/Actions/LogReturn.php`, `app/Actions/HandleReturn.php` | Returns: statuses, reasons, the lot cap and return window (`Listing::returnableQuantity()`, `acceptsReturns()`), and the allowed status moves. Buyer UI: `app/Livewire/Listings/Returns.php`; admin page: `app/Livewire/Admin/Returns/Index.php`; replacement note PDF: `app/Http/Controllers/ReplacementNoteController.php` and `resources/views/returns/note-pdf.blade.php`. Settings: `config/returns.php`. |
| `app/Support/Money.php` | `Money::format()` for `R1,250.00` formatting. |
| `app/Invoices/Invoice.php`, `app/Http/Controllers/InvoiceController.php` | Invoice number, dates and amount, and the PDF download (winner and admins only). Template: `resources/views/invoices/pdf.blade.php`. Settings: `config/invoice.php`. |
| `resources/views/components/share-invoice.blade.php` | The invoice **Share** button: fetches the PDF and opens the browser's share menu (Web Share API), or downloads the PDF where sharing files isn't supported. |
| `config/auction.php` | `min_increment` (default R10, used when a listing doesn't set its own), `default_duration_hours` (default 12) and `ending_soon_minutes` (default 60). |
| `database/seeders/` | Demo users, listings and bids. |
| `public/manifest.webmanifest`, `public/sw.js` | Installable app setup and service worker. |

### Database

- **users**: plus `is_admin`, `deactivated_at` and `last_login_at`.
- **listings**: `title`, `description`, `min_price`, `buy_now_price` (empty = no Buy Now), `min_increment` (empty = the default increment), `quantity`, `ends_at`, `status` (`active`/`closed`), `winner_id`.
- **bids**: `listing_id`, `user_id`, `amount`, `is_buy_now` (the bid that bought the listing with Buy Now), `voided_at` (set when a bid is withdrawn).
- **notifications**: Laravel's standard in-app notifications table (`data` holds the title, message and listing; `read_at` is empty until read).
- **listings.ending_soon_notified_at**: when the "ending soon" notification went out, so it's only sent once.
- **return_requests**: `listing_id`, `user_id` (the buyer), `quantity_requested`, `quantity_approved`, `reason`, `details`, `status` (`requested`/`approved`/`received`/`replaced`/`rejected`/`cancelled`), `admin_note`, `handled_by`, `replaced_at`.

On a live database that's already installed, import `database/dumps/ppbid-notifications-upgrade.sql`, `ppbid-last-login-upgrade.sql`, `ppbid-returns-upgrade.sql`, `ppbid-buy-now-upgrade.sql` and `ppbid-bid-increment-upgrade.sql` once each, in that order, to add these (or `ppbid-fields-only.sql` once instead of all five).

### Tests

```bash
./vendor/bin/sail test
```

Tests use Pest and PHPUnit and run against a separate `testing` database, so they don't touch your data. Code style is checked with:

```bash
./vendor/bin/pint --test
```

---

## 8. Troubleshooting

| Problem | Fix |
|---|---|
| Page looks unstyled or new styles are missing | Run `npm run build`. |
| Listings past their end time still show "Ended, closing" | The scheduler isn't running. Run `sail artisan auctions:close` or set up the cron entry above. |
| Times look two hours off | `APP_TIMEZONE` in `.env` should be `Africa/Johannesburg`, or left out (that's the default). |
| "This account has been deactivated" at login | An admin needs to reactivate the account under **Users**. |
| A user forgot their password | An admin sets a new one under **Users → Edit**. |
| "Log in as" button is missing | It's only shown for active bidders, not for admins, deactivated users or yourself. |
| Invoice shows "ppbid" or no address or banking details | Fill in the `INVOICE_*` settings in `.env`. On a server with cached config, run `php artisan config:clear` afterwards. |
| Invoice download gives an error on the server | Upload the whole `vendor/` folder again (it now includes the PDF library), and make sure `storage/` is writable. |
| No "ending soon" or "you won" notifications | The scheduler isn't running. Set up the cron entry in section 5. |
| "Log a return" is missing | It's only shown to the winner, inside the return window (`RETURNS_WINDOW_DAYS`, default 7 days after the auction ends), and while some units of the lot aren't already in an open return. |
| Return pages give a database error on the server | The `return_requests` table is missing. Import `database/dumps/ppbid-returns-upgrade.sql` once. |
| Port 8000 or 3307 already in use | Change `APP_PORT` or `FORWARD_DB_PORT` in `.env`, then `sail up -d` again. |
