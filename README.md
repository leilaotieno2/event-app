# Event Registration System (PHP + HTML + MySQL)

A complete web application for registering users for academic/community events.

## Features

- User registration & login with secure (bcrypt) password hashing
- Admin dashboard: create, edit, delete events
- Duplicate-registration prevention (DB constraint + transaction lock)
- Real-time "slots remaining" counter (polled via a JSON API every 5s)
- Client-side AND server-side input validation
- Relational (MySQL/MariaDB) storage via PDO
- Defenses against SQL Injection, XSS, and CSRF (see below)

## Folder Structure

```
event-app/
├── admin/
│   ├── index.php          Admin dashboard (list/edit/delete links)
│   ├── create_event.php   Create event form + handler
│   ├── edit_event.php     Edit event form + handler
│   └── delete_event.php   Confirm + delete (POST + CSRF only)
├── api/
│   └── slots.php          JSON endpoint: live slots remaining
├── config/
│   └── database.php       PDO connection
├── includes/
│   ├── functions.php       clean_input(), e(), CSRF helpers, flash messages
│   ├── auth.php            login/role helpers
│   ├── header.php / footer.php
├── css/style.css
├── sql/schema.sql          Database schema
├── index.php               Public event listing
├── event.php               Event detail + registration
├── register.php            User sign-up
├── login.php                User login
└── logout.php
```

## Setup

1. Create the database: run `sql/schema.sql` in MySQL/MariaDB (via phpMyAdmin,
   `mysql -u root -p < sql/schema.sql`, or similar).
2. Edit `config/database.php` with your DB host/user/password.
3. Create an admin account: either register a normal account and then run
   `UPDATE users SET role='admin' WHERE email='you@example.com';`, or generate
   a bcrypt hash with `php -r "echo password_hash('YourPass123', PASSWORD_BCRYPT);"`
   and insert it directly (see the commented INSERT at the bottom of schema.sql).
4. Point your web server (Apache/Nginx with PHP-FPM, or `php -S localhost:8000`
   from the project root for quick local testing) at the project folder.
5. Visit `/register.php` to create a user account, or `/login.php` if you
   already have one.

## How Each Requirement Is Met

| Requirement | Where / How |
|---|---|
| Secure registration/login | `register.php`, `login.php` — `password_hash()` / `password_verify()` (bcrypt), `session_regenerate_id()` on login |
| Admin CRUD on events | `admin/create_event.php`, `admin/edit_event.php`, `admin/delete_event.php`, gated by `require_admin()` |
| Prevent duplicate registrations | `registrations` table has `UNIQUE(user_id, event_id)`; `event.php` also pre-checks and uses a `SELECT ... FOR UPDATE` transaction to close the race condition between "check" and "insert" |
| Real-time available slots | `api/slots.php` returns JSON; `index.php` and `event.php` poll it every 5 seconds via `fetch()` and update the badge without a page reload |
| Client + server validation | HTML5 attributes (`required`, `pattern`, `minlength`, `type=email`) plus small JS checks on the client; every field is re-validated in PHP (see `register.php`, `admin/create_event.php`) — the server never trusts the client |
| Relational database | MySQL/MariaDB via PDO, schema in `sql/schema.sql`, with foreign keys and a `UNIQUE` constraint enforcing data integrity |

## Security Explanation

### 1. SQL Injection

Every single database query in the application uses **PDO prepared
statements with bound parameters** (`$pdo->prepare(...)` + `execute([...])`).
User input is never concatenated directly into a SQL string. In
`config/database.php`, `PDO::ATTR_EMULATE_PREPARES` is explicitly set to
`false`, which forces PHP to send the query and the parameters to MySQL
**separately** — the database itself keeps code and data apart, so even a
malicious string like `' OR '1'='1` is only ever treated as a literal value,
never as SQL syntax. Numeric inputs such as event IDs are additionally
validated with `filter_var(..., FILTER_VALIDATE_INT)` before use.

### 2. Cross-Site Scripting (XSS)

- **Output escaping**: every value that originates from a user or the
  database is passed through the `e()` helper
  (`htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`) before being echoed into
  HTML. This converts characters like `<`, `>`, `"` and `'` into harmless
  HTML entities, so injected `<script>` tags render as visible text rather
  than executing.
- **Input sanitisation on entry**: `clean_input()` trims input and strips
  tags with `strip_tags()` as an additional layer for fields like name,
  email, and location (defense in depth — the real protection is the output
  escaping above, since sanitising input alone is never sufficient).
- **HttpOnly session cookies**: set in `includes/functions.php`, so even if
  an XSS payload somehow executed, JavaScript could not read the session
  cookie to hijack the session.
- **Content-Security-Policy header**: `send_security_headers()` sends a CSP
  that only allows scripts from the same origin (`script-src 'self'`),
  which blocks inline/injected `<script>` payloads and scripts loaded from
  attacker-controlled domains.

### 3. Cross-Site Request Forgery (CSRF)

- A **random, unpredictable token** (`bin2hex(random_bytes(32))`) is
  generated once per session (`csrf_token()`) and embedded as a hidden field
  in every state-changing form: registration, login, event registration,
  and all admin create/edit/delete actions (`csrf_field()`).
- On every `POST` request, `verify_csrf()` checks the submitted token
  against the one stored in the session using `hash_equals()` (a
  timing-attack-resistant comparison) and immediately rejects the request
  with a 403 if it doesn't match.
- Destructive actions (like deleting an event) are **never performed via a
  plain GET link** — the delete page only shows a confirmation form; the
  actual deletion happens on `POST` with a valid CSRF token, so a malicious
  `<img src="delete_event.php?id=1">` on another site cannot trigger it.
- The session cookie is also set with `SameSite=Lax`, an additional
  browser-level layer that stops the cookie being sent on most
  cross-site requests in the first place.

### Other hardening included

- `X-Content-Type-Options: nosniff` and `X-Frame-Options: DENY` headers to
  reduce MIME-sniffing and clickjacking risk.
- Generic error messages for login failures (`Invalid email or password`)
  so attackers can't enumerate which emails are registered.
- Simple session-based rate limiting on login attempts to slow brute force.
- Database errors are logged server-side (`error_log`) and never shown to
  the browser, preventing information disclosure.
