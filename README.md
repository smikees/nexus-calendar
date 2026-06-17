# Nexus Calendar

A self-hosted, multi-calendar web app running at **[cal.stamih.com](https://cal.stamih.com)**.

Guests can browse curated **public** calendars (e.g. a full 2026 World Cup schedule); signed-in
users get **private** calendars they own, can share, and can sync to/from the outside world via
iCalendar feeds. No build step, no framework runtime — just PHP, MySQL, and vanilla JS.

---

## Features

- **Multiple calendars** with per-calendar color and emoji icon. Month / Week / Day / Agenda views
  (FullCalendar v6), light & dark mode with four accent themes.
- **Events** — create, edit, drag/resize, all-day and timed; overlapping timed events tile
  side-by-side (Outlook-style); each event shows a faded calendar/event icon motif.
- **Recurrence** — daily / weekly / monthly / yearly with interval, weekly by-day, monthly
  "first Tuesday"-style (`BYDAY=1TU`, last weekday, etc.), and an end condition (never / after N /
  until date). Stored as RFC 5545 `RRULE` and expanded client-side.
- **Per-event emoji** icons (overrides the calendar's icon when set).
- **Sharing** — owners can share private calendars with other users by email as viewer or editor.
- **Import** — bring events in from any `.ics`:
  - **Subscribe to a URL** → a read-only calendar that mirrors an external feed and re-syncs
    lazily (when stale) plus a manual *Refresh*.
  - **Upload an .ics file** → one-time import into a new or existing calendar.
- **Export** — per-calendar **Download .ics**, plus an opt-in **public subscription link**
  (a secret-token URL that Google / Apple / Outlook can subscribe to and auto-refresh).
- **Preferences** (enabled calendars, order, view) persist in `localStorage`; drag-and-drop to
  reorder calendars.

## Tech stack

- **Backend:** PHP (PDO/MySQL), no framework. Small helper libraries in `lib/`.
- **Auth:** Google OAuth 2.0; sessionless, signed **HMAC cookie** (`cal_auth`). Admin emails are
  configured in `config.php`.
- **Frontend:** vanilla JS (`assets/app.js`) + FullCalendar v6 (vendored under
  `assets/vendor/`, incl. the rrule plugin). No bundler; assets are `?v=` cache-busted.
- **iCalendar:** a focused, dependency-free reader/writer in `lib/ical.php` (parse + generate +
  SSRF-guarded feed fetch).
- **DB:** MySQL/InnoDB, `utf8mb4`.

## Project structure

```
index.html                 App shell + modals
assets/
  app.js                   All client logic
  app.css                  Themed styles
  vendor/                  FullCalendar v6 + rrule plugin
lib/
  bootstrap.php            config(), db() (PDO), json_out()
  auth.php                 Google OAuth + HMAC cookie helpers, current_user(), is_admin()
  calendars.php            visibility, permissions, slug/uid helpers, feed helpers
  ical.php                 iCalendar parse/generate + guarded fetch
api/
  calendars.php            GET visible calendars (+ flags) and the current user
  events.php               GET events for a date range (recurring emitted as RRULE)
  event.php                POST/PATCH/DELETE an event
  calendar.php             create/rename/recolor/delete a private calendar
  shares.php               list/add/remove calendar shares
  import.php               POST .ics file import
  subscribe.php            POST subscribe to an .ics feed URL (creates a mirror)
  feed_sync.php            POST re-sync a subscribed calendar
  export.php               GET .ics (auth download by id, or public feed by token)
  feed_token.php           POST enable/regenerate/disable a calendar's public link
auth/                      login / callback / logout
admin/                     seed.php (sample/public data, admin only)
db_setup.sql               Schema
db_migrate_phase4.sql      avatar_url + calendar_shares
db_migrate_phase5.sql      events.icon (per-event emoji)
db_migrate_phase6.sql      calendars.feed_url / feed_last_synced / feed_etag (subscriptions)
```

## Getting started (self-hosting)

1. **Database** — create a MySQL database + user, then run `db_setup.sql`, followed by the
   `db_migrate_phase*.sql` files in order.
2. **Config** — copy `config.example.php` to `config.php` **on the server** (never commit it; it's
   git-ignored and blocked by `.htaccess`) and fill in DB credentials, Google OAuth client
   id/secret + redirect URI, a long random `app_secret` (HMAC key), and `admin_emails`.
3. **Google OAuth** — in Google Cloud Console create an OAuth client and add the redirect URI
   (`https://<your-host>/auth/callback.php`).
4. **Sample data** *(optional)* — sign in as an admin and hit `/admin/seed.php` to load the demo
   public calendars (Finance + 2026 World Cup).

### iCalendar notes

- Times are stored in **UTC**. All-day events use an **exclusive** end date (matching RFC 5545).
- The parser handles `TZID=` (IANA + common Windows zone names), UTC `Z`, all-day `VALUE=DATE`,
  `DURATION`, and `RRULE`. Not yet handled: `EXDATE` and per-occurrence overrides (`RECURRENCE-ID`).
- Outbound feed fetches are SSRF-guarded (http/https/webcal only, private/loopback/reserved IPs
  blocked, 5 MB cap, timeouts, ETag/`304` support).

## Security notes

- Write endpoints require a logged-in user and a `X-Requested-With` header (CSRF guard; the auth
  cookie is `SameSite=Lax`).
- Public subscription links use an unguessable random token and are opt-in per calendar; tokens are
  only ever returned to the calendar's manager.
- Subscribed (feed) calendars are read-only mirrors — manual event writes to them are rejected.

## Deployment

Pushes to `main` auto-deploy to the cPanel host via FTP (GitHub Actions,
`.github/workflows/deploy.yml`). Required repo secrets:

| Secret | Value |
|---|---|
| `FTP_SERVER` | FTP server hostname |
| `FTP_USERNAME` | cPanel FTP account user |
| `FTP_PASSWORD` | that account's password |

The FTP account is scoped to the `cal.stamih.com` document root, so the workflow uploads to `./`.
HTML is served `no-cache` and assets are `?v=` cache-busted so deploys reach users immediately.
**Database migrations are manual** (run the relevant `db_migrate_phase*.sql` in phpMyAdmin); the app
degrades gracefully when a migration hasn't run yet.

## License

See [LICENSE](LICENSE).
