# ppbid

A simple auction app built with Laravel 12, Livewire, Flux and MySQL. Amounts are in South African Rand.

- There is no public sign-up and no "forgot password". Admins add users and set or reset their passwords at `/admin/users`.
- Users log in, browse listings and bid. Other users only ever see bidder numbers (e.g. "Bidder #014").
- Each listing is sold as one lot: a bid is the price for the full quantity.
- A bid must be at least the minimum price, and beat the current top bid by at least the increment. Admins can set a **bid increment per listing** (e.g. R50 for a pallet of bricks); listings without one use the default, R10 (`AUCTION_MIN_INCREMENT`). The listing page shows the increment, and it's locked once the listing has bids. Bidders can use the **−** / **+** buttons next to the amount (or the ↑ / ↓ keys) to step their bid by the increment instead of typing it; it never goes below the minimum next bid.
- A listing can have an optional **Buy Now price**. Any bidder can pay it to win the lot straight away, which ends the auction. See [Buy Now](#buy-now).
- Admins create, edit and close listings at `/admin/listings`. Admins can't bid.
- New listings expire **12 hours** after they're created by default (the admin can pick another time on the form). Change the default with `AUCTION_DEFAULT_DURATION_HOURS`.
- Listing pages refresh every 5 seconds.
- In-app notifications (bell icon) for outbids, auctions ending soon, wins and losses. The app sends **no emails**.
- The winner can download a **PDF invoice** for each auction they win.
- Admins can **log in as** a bidder to see the app exactly as they do (read-only).
- Admins can see each user's **last login** time on the Users page.
- Winners can **log a return** for faulty units (e.g. RAM that fails testing). Faulty units are **replaced, never refunded**. Admins handle returns at `/admin/returns`.

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

The seeder also creates 16 fake listings with bid histories: open ones (one ending in 10 minutes, some with no bids yet, four with a Buy Now price) and closed ones (with winners, one with no bids, and one Alice bought with Buy Now). Alice is set up to be winning, outbid, a winner and a loser, so every status shows on "My bids". Re-seed at any time with:

```bash
./vendor/bin/sail artisan migrate:fresh --seed
```

Run the tests with `./vendor/bin/sail test`.

## Notifications (in-app only)

Notifications appear under the **bell** in the sidebar (and in the phone header), with an unread count, and on the **Notifications** page (`/notifications`). A small pop-up also appears when a new one arrives. The bell checks for new notifications every 15 seconds. Clicking a notification marks it read and opens its listing.

| Notification | Who gets it | When |
|---|---|---|
| You've been outbid | The bidder who was leading | Someone places a higher bid (only the latest unread one per listing is kept) |
| Auction ending soon | Everyone who bid on the listing | Once, when an hour or less is left (`AUCTION_ENDING_SOON_MINUTES`, default 60) |
| You won! | The winner | The listing closes, or they buy it with Buy Now |
| Auction ended / closed early | Every other bidder | The listing closes. After a Buy Now it says who bought it (as a bidder number) and for how much |
| Listing closed / closed early / bought now | Admins (except the admin who pressed "Close now") | The listing closes, with the winner's name and amount |

- Notifications are stored in the `notifications` table and are **never emailed**. The forgot-password and email-verification pages have been removed, so the app sends no email at all.
- Bidders only ever see other bidders as bidder numbers; only admin notifications include names.
- Deactivated users get no notifications.
- "Ending soon" and "won/lost" notifications come from the scheduler (`auctions:notify-ending-soon` and `auctions:close`, both every minute), so the cron entry below must be set up. Changing a listing's end time re-arms its "ending soon" notification.

Code: `app/Notifications/`, `app/Livewire/NotificationBell.php`, `app/Livewire/Notifications.php`, `app/Console/Commands/NotifyEndingSoon.php`.

## Last login

The admin **Users** list has a **Last login** column ("3 hours ago", with the exact date and time on hover, or "Never"), and a user's edit page shows it under their name.

- It's saved in `users.last_login_at` every time a user logs in, including automatic "remember me" logins after their session expired.
- Not counted: failed logins, attempts by deactivated accounts, and an admin switching into or back from **Log in as**.
- Recording it doesn't change the user's `updated_at`.

Code: `app/Listeners/RecordLastLogin.php` (listens to Laravel's `Login` event).

## Logging in as a user (impersonation)

Admins can see the app exactly as a bidder sees it, which helps when answering "why can't I see…?" questions.

- **Start:** Admin → **Users** → **Log in as** (on the user's row or their edit page). It isn't offered for other admins, deactivated users or yourself.
- While logged in as someone, an **amber banner** at the top of every page says who you're viewing as, with a **Return to admin** button (also in the user menu).
- It's **read-only**: bidding is turned off, the profile and password settings pages are blocked, and opening notifications doesn't mark them as read for the user. The admin area is closed until you return.
- **Return to admin** logs you straight back into your admin account. If your admin access was removed meanwhile, you're logged out instead. **Log out** while viewing as someone logs you out completely.
- Nothing is stored in the database: the admin's id is kept in the session. Each start and stop is written to the Laravel log (`storage/logs/laravel.log`) with both users' ids and emails.

Code: `app/Support/Impersonation.php`, `app/Http/Controllers/ImpersonationController.php`, `app/Http/Middleware/BlockWhileImpersonating.php`.

## Buy Now

Admins can give a listing an optional **Buy Now price** on the create/edit form at `/admin/listings`. Leave it empty for no Buy Now.

- It must be higher than the minimum price. Unlike the minimum price, it can be added, changed or removed while bidding is running, but once there are bids it must be at least the top bid plus the increment.
- While the listing is open, bidders see **Buy now for R…** on the listing page (and a Buy now badge on the listings page). Clicking it asks them to confirm; only then do they win the whole lot, the auction ends straight away and their invoice is ready.
- Bids at or above the Buy Now price aren't allowed: bidders are told to use Buy Now instead.
- The current leader can use Buy Now too. Admins, deactivated users and "Log in as" sessions can't.
- If the admin changes the price while a bidder has the confirmation open, the purchase is refused and the bidder is shown the new price, so nobody pays a price they didn't see.
- A Buy Now is saved as a bid at the Buy Now price (marked **Buy now** in the bid history), so the winner, invoice, "My bids" and returns work as for any won auction. Other bidders and admins are notified that it was bought with Buy Now.

Code: `app/Actions/BuyNow.php`, `Listing::buyNowAvailable()`, the Buy Now rule in `app/Actions/PlaceBid.php`.

## Invoices

When an auction closes with a winner, a PDF invoice is available straight away. Nothing extra is stored in the database: the invoice is built from the closed listing each time it's downloaded.

- **Who can download it:** the winner (the **Download invoice** button on the listing page, and **Invoice** on My bids) and admins (on the listing page and in **Manage listings**). Nobody else can.
- **Share (WhatsApp etc.):** next to each invoice button, the winner and admins get a **Share** button. On a phone it opens the phone's share menu with the invoice PDF attached, so you can pick WhatsApp (or email, etc.) and a chat. There's no WhatsApp integration: it uses the browser's built-in share feature. Where a browser can't share files (most desktop browsers), the PDF is downloaded instead, to attach by hand. The share menu only works over **HTTPS** (or on `localhost`), so test it on the live site or through an HTTPS tunnel, not via a plain `http://` network address.
- **Invoice number:** comes from the listing, e.g. listing 123 is `INV-000123`. It's also the payment reference.
- **Amount:** the winning bid, for the whole lot. **No VAT** is charged, so the winning bid is the total due.
- **Dates:** the invoice date is when the auction ended; payment is due `INVOICE_PAYMENT_TERMS_DAYS` days later (default 7).
- **Seller details** (company name, address, phone, email, registration number, banking details for EFT, footer note) come from the `INVOICE_*` settings in `.env`; see `.env.example`. Use `|` to split the address over lines, e.g. `INVOICE_COMPANY_ADDRESS="12 Main Road|Germiston|1401"`. The banking box is left out until bank details are set.
- The PDF is made with [barryvdh/laravel-dompdf](https://github.com/barryvdh/laravel-dompdf) (pure PHP, works on shared hosting). The template is `resources/views/invoices/pdf.blade.php`.

Code: `app/Invoices/Invoice.php`, `app/Http/Controllers/InvoiceController.php`, `config/invoice.php`. Share button: `resources/views/components/share-invoice.blade.php`.

## Returns (replace faulty units)

Stock is mostly RAM, so some units can be faulty. The winner of an auction can log a return for part of the lot, and an admin replaces those units. **There are no refunds**: the invoice never changes.

- **Logging a return:** on the listing page of an auction they won (the **Returns** section, or **Return** on My bids), the winner enters:
  - **Faulty units**: how many, up to the units of the lot not already in an open return. Example: a lot of 20 with 3 in an open return leaves 17.
  - **What's wrong**: Not detected / won't boot, Fails memory test, Wrong spec, Physical damage, or Other.
  - **Details** (optional): e.g. "2 sticks fail MemTest86 at 2400MHz".
- **Not while impersonating:** an admin using **Log in as** sees the buyer's returns but can't log or cancel one (the page says so).
- **Return window:** returns can be logged for `RETURNS_WINDOW_DAYS` days after the auction ends (default 7). Replacing units doesn't restart it.
- **Return number:** `RET-000045` (from the return's id).
- **Statuses:** **Requested** → **Approved** (or **Rejected**) → **Received** → **Replaced**. An admin can go from Approved straight to Replaced when units are swapped over the counter. The buyer can **cancel** a return while it's still Requested.
- **Approving fewer:** the admin can approve fewer units than were logged (e.g. 2 of 3 failed testing here). Rejecting needs a reason. The note is shown to the buyer.
- **Open returns count against the lot.** Once a return is Replaced, Rejected or Cancelled its units are free again, so a replacement unit that also fails can be logged again (inside the window).
- **No replacement stock?** Replace with a similar or better module and say so in the note.
- **Notifications (in-app):** admins get "Return logged"; the buyer gets one at each step (approved, rejected, received, replaced). Clicking an admin's notification opens the Returns page.
- **Replacement note:** once Replaced, the buyer and admins can download a PDF replacement note `RN-000045` from the listing page or the admin Returns page. It lists the units logged and replaced and refers to the original invoice.
- **Admin page:** `/admin/returns` (sidebar **Returns**, with a count of open returns). Filter by status (Open by default) and search by listing, buyer or RET number.

Code: `app/Models/ReturnRequest.php`, `app/Actions/LogReturn.php`, `app/Actions/HandleReturn.php`, `app/Livewire/Listings/Returns.php`, `app/Livewire/Admin/Returns/Index.php`, `app/Http/Controllers/ReplacementNoteController.php`, `resources/views/returns/note-pdf.blade.php`, `config/returns.php`. Table: `return_requests`.

## Closing expired auctions

Listings past their end time stop accepting bids immediately. The `auctions:close` command then marks them closed and records the winner (the highest bid; on a tie, the earliest):

```bash
php artisan auctions:close
```

It is scheduled to run every minute in `routes/console.php`, together with `auctions:notify-ending-soon`. For the schedule to run, add this cron entry on the server:

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

## Database files for shared hosting

SQL files in `database/dumps/` (not committed to git) for importing with phpMyAdmin:

- `ppbid-clean.sql`: a fresh install with all tables and one admin (`admin@test.com` / `password`).
- `ppbid-demo.sql`: a fresh install with the demo users, listings, bids and notifications.
- `ppbid-notifications-upgrade.sql`: for a live database that's already installed. Import it **once** to add the notifications table and the `listings.ending_soon_notified_at` column. Existing data is kept.
- `ppbid-last-login-upgrade.sql`: then import this **once** to add `users.last_login_at`. Existing data is kept.
- `ppbid-returns-upgrade.sql`: then import this **once** to add the `return_requests` table. Existing data is kept.
- `ppbid-buy-now-upgrade.sql`: then import this **once** to add `listings.buy_now_price` and `bids.is_buy_now`. Existing data is kept.
- `ppbid-bid-increment-upgrade.sql`: then import this **once** to add `listings.min_increment`. Existing data is kept.
- `ppbid-fields-only.sql`: all of the above schema changes in one file (notifications, ending soon, last login, returns, Buy Now, bid increment). Use it **instead of** the upgrade files, not as well.

Upgrade files must be imported in the order above, and each only once. A fresh install from `ppbid-clean.sql` or `ppbid-demo.sql` already includes everything.
