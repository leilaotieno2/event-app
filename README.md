# EventReg — Event Registration Platform

A production-ready event registration platform: browse and search events,
register with real-time availability, join waitlists when full, check in
at the door with a personal code, and get help from a built-in AI
assistant — all with an admin dashboard for organizers.

## Features

- **Discovery** — search by keyword, filter by category, live "slots
  remaining" badges (polled every 5s, no page reload)
- **Registration** — secure signup/login (bcrypt), duplicate-registration
  prevention (DB constraint + row-level locking, works on MySQL and SQLite)
- **Waitlists** — join automatically when an event is full
- **Check-in** — every registration gets a unique code; `admin/checkin.php`
  looks it up and marks attendance at the door
- **Calendar export** — one-click `.ics` download for any event
- **AI features** (see below) — description generation, an event Q&A
  assistant, and personalised "recommended for you" events
- **Admin dashboard** — create/edit/delete events, analytics (fill rates,
  registrations by category, check-in rate)
- **Security** — see [Security Explanation](#security-explanation) below

## AI Features

Three AI-assisted features are built in, each with a real-provider path and
a deterministic **stub mode** so the app is fully functional and demoable
with zero API cost or setup:

| Feature | Where | Behaviour with no `AI_API_KEY` | Behaviour with a key |
|---|---|---|---|
| Description generator | Admin create/edit event form ("✨ Generate with AI") | Rule-based template using title/category/location/date | Real model call, genuinely written copy |
| Event assistant | "💬 Ask AI" widget on every event page | Keyword-matched FAQ answers from that event's own data | Real model call, grounded in the same event data |
| Recommendations | "Recommended for you" on the homepage | Rule-based: same category as events you've registered for | Same interface, ready to swap in embeddings/similarity |

See `config/ai.php` — every function falls back gracefully, so adding a key
later (Anthropic or OpenAI-compatible) requires no other code changes.

## Folder Structure

```
event-app/
├── admin/
│   ├── index.php          Admin dashboard (list/edit/delete + waitlist counts)
│   ├── create_event.php   Create event form + AI description generator
│   ├── edit_event.php     Edit event form + AI description generator
│   ├── delete_event.php   Confirm + delete (POST + CSRF only)
│   ├── checkin.php        Look up a check-in code, mark attendance
│   └── analytics.php      Fill rates, registrations by category, check-in rate
├── api/
│   ├── slots.php                    JSON: live slots remaining
│   ├── calendar.php                 .ics calendar export
│   ├── ai_generate_description.php  AI description generation (admin-only)
│   └── ai_assistant.php             AI event Q&A assistant
├── config/
│   ├── database.php        PDO connection (MySQL or SQLite via .env)
│   ├── env.php              Minimal .env loader
│   └── ai.php               AI abstraction layer (live call + stub fallback)
├── includes/
│   ├── functions.php       clean_input(), e(), CSRF + CSP nonce helpers, flash messages
│   ├── auth.php            login/role helpers
│   ├── header.php / footer.php
├── css/style.css           Design system (hero, cards, chips, AI widget, etc.)
├── sql/
│   ├── schema.sql               MySQL schema (fresh installs)
│   ├── schema_sqlite.sql        SQLite equivalent (local/demo)
│   ├── migrations/              Incremental migrations for existing MySQL DBs
│   └── seed_sqlite.php          Seeds a local SQLite DB with demo data
├── index.php               Public event listing, search/filter, recommendations
├── event.php               Event detail, registration, waitlist, AI widget
├── register.php            User sign-up
├── login.php                User login
└── logout.php
```

## Setup

### Option A: SQLite (zero setup, recommended for trying it out)

```
cp .env.example .env        # DB_DRIVER=sqlite by default
php sql/seed_sqlite.php     # creates + seeds sql/event_registration.sqlite
php -S localhost:8000
```

Visit `http://localhost:8000`. Log in as `admin@example.com` /
`Admin@1234`, or register a new account.

### Option B: MySQL/MariaDB (production)

1. Run `sql/schema.sql` against your database (or `sql/migrations/*.sql` in
   order if upgrading an existing installation).
2. `cp .env.example .env` and set `DB_DRIVER=mysql` plus your `DB_HOST`,
   `DB_NAME`, `DB_USER`, `DB_PASS`.
3. Create an admin account: register normally, then
   `UPDATE users SET role='admin' WHERE email='you@example.com';`.
4. Point your web server (Apache/Nginx + PHP-FPM) at the project root.

### Enabling real AI responses

Set `AI_PROVIDER` (`anthropic` or `openai`), `AI_API_KEY`, and `AI_MODEL` in
`.env`. Leave `AI_API_KEY` blank to keep running in stub mode.

## How Each Requirement Is Met

| Requirement | Where / How |
|---|---|
| Secure registration/login | `register.php`, `login.php` — `password_hash()` / `password_verify()` (bcrypt), `session_regenerate_id()` on login |
| Admin CRUD on events | `admin/create_event.php`, `admin/edit_event.php`, `admin/delete_event.php`, gated by `require_admin()` |
| Prevent duplicate registrations | `registrations` table has `UNIQUE(user_id, event_id)`; `event.php` also pre-checks and uses a locking transaction (`FOR UPDATE` on MySQL, `BEGIN IMMEDIATE` on SQLite) to close the race condition between "check" and "insert" |
| Real-time available slots | `api/slots.php` returns JSON; `index.php` and `event.php` poll it every 5 seconds via `fetch()` and update the badge without a page reload |
| Client + server validation | HTML5 attributes (`required`, `pattern`, `minlength`, `type=email`) plus small JS checks on the client; every field is re-validated in PHP — the server never trusts the client |
| Relational database | MySQL/MariaDB (production) or SQLite (local/demo) via PDO, with foreign keys and `UNIQUE` constraints enforcing data integrity |

## Security Explanation

### 1. SQL Injection

Every single database query in the application uses **PDO prepared
statements with bound parameters** (`$pdo->prepare(...)` + `execute([...])`).
User input is never concatenated directly into a SQL string. In
`config/database.php`, `PDO::ATTR_EMULATE_PREPARES` is explicitly set to
`false`, which forces PHP to send the query and the parameters to the
database **separately**. Numeric inputs such as event IDs are additionally
validated with `filter_var(..., FILTER_VALIDATE_INT)` before use.

### 2. Cross-Site Scripting (XSS)

- **Output escaping**: every value that originates from a user or the
  database is passed through the `e()` helper
  (`htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`) before being echoed into
  HTML.
- **Input sanitisation on entry**: `clean_input()` trims input and strips
  tags as an additional layer (defense in depth).
- **HttpOnly session cookies**, so JavaScript cannot read the session cookie
  even if XSS somehow executed.
- **Content-Security-Policy**: `send_security_headers()` sends a CSP that
  only allows same-origin scripts, plus a **per-request nonce** for the
  handful of inline `<script>` blocks the app uses (real-time polling, the
  AI widget). This is stricter than `'unsafe-inline'` — an attacker-injected
  `<script>` tag has no way to know the nonce, so it still won't execute.

### 3. Cross-Site Request Forgery (CSRF)

- A **random, unpredictable token** (`bin2hex(random_bytes(32))`) is
  generated once per session and embedded in every state-changing form
  *and* every state-changing `fetch()` call (AI endpoints included).
- On every `POST`, `verify_csrf()` checks the submitted token against the
  session's token using `hash_equals()` (timing-attack-resistant) and
  rejects the request with a 403 on mismatch.
- Destructive actions (deleting an event) are never triggered by a plain
  `GET` link — only a confirmed `POST` with a valid token.
- Session cookies are set with `SameSite=Lax` as an additional layer.

### Other hardening

- `X-Content-Type-Options: nosniff` and `X-Frame-Options: DENY`.
- Generic login error messages (no email enumeration) + simple session-based
  rate limiting on login attempts and AI assistant questions.
- Database errors are logged server-side and never shown to the browser.
- Credentials and API keys live in `.env` (gitignored), never in code.
