# Nexus Calendar — Project Plan

Phased, each phase independently deployable through the existing pipeline. Complexity
kept minimal; see `DESIGN.md` for the architecture these phases implement.

---

## Phase 0 — Manual setup (you), before any code

These are the only things I can't do for you (they involve credentials and cPanel/Google
account settings). Everything after this I can build and deploy.

1. **Create the database** — cPanel → MySQL Databases:
   - DB: `t2hu9otd1ek3_nexuscal`
   - A DB user with all privileges on it; note the username/password.
2. **Create the Google OAuth client** — Google Cloud Console → Credentials → OAuth 2.0 Client ID (Web):
   - Authorized redirect URI: `https://cal.stamih.com/auth/callback.php`
   - Note the **Client ID** (you can share it) and **Client Secret** (goes only into
     `config.php` on the server — don't paste it in chat).
3. **Create `config.php` on the server** (cPanel File Manager, in the doc root) from the
   `config.example.php` I'll provide in Phase 1. It will hold: DB creds, Google client
   id/secret, an app HMAC secret, and the **admin email allowlist** (your email).
4. Confirm your admin email so I can wire the allowlist check.

> I'll never enter credentials or create accounts myself — these four steps are yours.
> Once they're done, hand me the Client ID, DB name/user, and admin email, and I take it from there.

---

## Phase 1 — Foundations
**Goal:** auth works end-to-end on a deployed build.
- `config.example.php`, PDO bootstrap (`db.php`), `db_setup.sql` (schema from DESIGN §4).
- Google login reusing the Dash pattern: `/auth/login.php`, `/auth/callback.php`, `/auth/logout.php`.
- App shell replacing the coming-soon page; "Sign in with Google" + signed-in state.
- CI: add `composer install --no-dev` step so `vendor/` (sabre/vobject) ships over FTP.
- **Done when:** I can log in/out on cal.stamih.com and the session persists via the HMAC cookie.

## Phase 2 — Public calendars, read path
**Goal:** a usable anonymous calendar.
- Seed a couple of public calendars + sample events.
- `GET /api/calendars`, `GET /api/events`; FullCalendar rendering (month/week/day/agenda).
- Enable/disable calendars, stored in `localStorage`; responsive desktop + mobile.
- **Done when:** a logged-out visitor browses public calendars and toggles them.

## Phase 3 — Hierarchy, theming, polish
**Goal:** the "delightful" layer.
- Priority ordering + day overflow ("+k more" → day view/popover).
- Per-calendar color/icon; recurring-event icon.
- Dark/light + preset themes via CSS variables; choice persisted.
- **Done when:** dense days read cleanly and theming/hierarchy are customizable (anon).

## Phase 4 — Private calendars (authenticated)
**Goal:** logged-in value.
- Create/import private calendars; event CRUD.
- In-place edit + drag/resize → `PATCH` (optimistic, with rollback).
- Persist enabled/hierarchy/theme server-side (`user_calendar_prefs`, `/api/prefs`).
- Admin can create/edit public calendars from the same UI.
- **Calendar sharing:** owner shares a private calendar with another user by email as
  `viewer` (read-only) or `editor` (CRUD events); `calendar_shares` table; shares resolve
  to the invitee on their next login; shared calendars appear in their sidebar. Owner-only
  manage; no re-sharing. (Added per request 2026-06-14.)
- **Done when:** I (logged in) manage my own calendars, share one with another account, and you (admin) manage public ones.

## Phase 5 — Import / export + AI format
**Goal:** interoperability.
- `.ics` export per calendar; `.ics` import (sabre/vobject).
- `GET /api/calendar.json` documented and stable; tokenized private feeds.
- **Done when:** a calendar round-trips through `.ics`, and `/api/calendar.json` returns the documented shape.

## Phase 6 — Notifications + PWA
**Goal:** reminders + installability.
- Web App Manifest; service worker (offline shell + client-side notifications).
- Opt-in notification flow for upcoming events.
- **Done when:** the app installs and fires a test notification.

## Phase 7 — Hardening + launch
- Input validation, window bounds on recurrence expansion, error handling, remove dev handlers.
- Light QA pass on mobile + desktop; accessibility check.
- **Done when:** no known correctness/security gaps; v1 announced.

---

## Immediate next steps

1. **You:** do Phase 0 (DB, Google OAuth client, `config.php`, admin email).
2. **You → me:** share the Client ID, DB name/user, and admin email (keep the Client
   Secret and DB password for `config.php` only).
3. **Me:** build Phase 1 (schema + auth + shell), push, deploy, and we verify login on
   cal.stamih.com together.
4. Then iterate phase by phase, deploying each.

---

## Risks / watch-items
- **No cron/background** → notifications are client-side only (accepted in v1).
