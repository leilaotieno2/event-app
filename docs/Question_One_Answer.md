# Question One — Event Registration Web Application

**Design and implement a web application that allows users to register for
academic or community events.** *(3 marks)*

Stack: **PHP 8 + HTML/CSS/JS + MySQL/MariaDB (PDO)**. Source code is in this
repository (`event-app/`); this document maps each requirement to the code
and includes screenshots of the application actually running, taken from
VS Code (source) and a browser (live functionality).

> Note on screenshots: the app was run locally with PHP's built-in server
> against a SQLite database (`DB_DRIVER=sqlite`) purely so it could be
> demoed on a machine with no MySQL server installed. This is a same
> `config/database.php` file with one added conditional — the graded/
> production code path (`DB_DRIVER` unset) is unchanged and talks to MySQL
> exactly as described in the schema and security notes below.

---

## 1. Requirements → Implementation

| Requirement | Where / How |
|---|---|
| Secure registration & login | [`register.php`](../register.php), [`login.php`](../login.php) — `password_hash()` / `password_verify()` (bcrypt), `session_regenerate_id()` on login |
| Admin CRUD on events | [`admin/create_event.php`](../admin/create_event.php), [`admin/edit_event.php`](../admin/edit_event.php), [`admin/delete_event.php`](../admin/delete_event.php), gated by `require_admin()` |
| Prevent duplicate registrations | `registrations` table has `UNIQUE(user_id, event_id)`; [`event.php`](../event.php) pre-checks and wraps the insert in a transaction with `SELECT ... FOR UPDATE` to close the check-then-insert race condition |
| Real-time available slots | [`api/slots.php`](../api/slots.php) returns JSON; `index.php` / `event.php` poll it every 5 seconds via `fetch()` and update the badge without a page reload |
| Client + server validation | HTML5 attributes (`required`, `pattern`, `minlength`, `type=email`) plus small JS checks client-side; every field is re-validated in PHP — the server never trusts the client |
| Relational database | MySQL/MariaDB via PDO, schema in [`sql/schema.sql`](../sql/schema.sql), with foreign keys and a `UNIQUE` constraint enforcing integrity |

---

## 2. Screenshots — Browser (functionality working end-to-end)

### 2.1 Public event listing with live "slots remaining"
![Events list](screenshots/01_browser_events_list.png)

### 2.2 Registration form
![Register](screenshots/02_browser_register.png)

### 2.3 Client-side validation (mismatched password confirmation caught before submit)
![Client-side validation](screenshots/03_browser_register_validation.png)

### 2.4 Login form
![Login](screenshots/04_browser_login.png)

### 2.5 Event detail page — slots badge, register button
![Event detail](screenshots/05_browser_event_detail.png)

### 2.6 After registering — slots counter drops from 5/5 to 4/5 in real time
![Registered, slots updated](screenshots/06_browser_registered_slots_updated.png)

### 2.7 Duplicate registration attempt — blocked with a friendly message
![Duplicate blocked](screenshots/07_browser_duplicate_blocked.png)

### 2.8 Admin dashboard — create / edit / delete events
![Admin dashboard](screenshots/08_browser_admin_dashboard.png)

### 2.9 Admin — create event form
![Admin create event](screenshots/09_browser_admin_create_event.png)

### 2.10 Admin — edit event (form pre-filled with existing data)
![Admin edit event](screenshots/15_browser_admin_edit_event.png)

### 2.11 Admin — delete confirmation (state-changing action gated behind POST + CSRF, never a plain GET link)
![Admin delete confirm](screenshots/16_browser_admin_delete_confirm.png)

### 2.12 Raw JSON from the real-time slots API (`api/slots.php`)
![API JSON](screenshots/10_browser_api_slots_json.png)

---

## 3. Screenshots — VS Code (source code behind the functionality)

### 3.1 `config/database.php` — PDO with real prepared statements (SQL injection defense)
![database.php](screenshots/11_vscode_database_php_sqlinjection.png)

### 3.2 `event.php` — transaction + row lock preventing duplicate/over-booked registrations
![event.php](screenshots/12_vscode_event_php_duplicate_prevention.png)

### 3.3 `includes/functions.php` — output escaping (`e()`) and CSRF token generation
![functions.php](screenshots/13_vscode_functions_php_xss_csrf.png)

### 3.4 `register.php` — server-side validation re-checking every client-side rule
![register.php](screenshots/14_vscode_register_php_validation.png)

### 3.5 `sql/schema.sql` — `users` table (relational storage, `UNIQUE` email, bcrypt hash column)
![schema.sql users](screenshots/17_vscode_schema_sql_users.png)

### 3.6 `sql/schema.sql` — `registrations` table (`UNIQUE(user_id, event_id)` + foreign keys — the DB-level guarantee against duplicate registrations)
![schema.sql registrations](screenshots/18_vscode_schema_sql_registrations.png)

### 3.7 `api/slots.php` in VS Code, with the integrated terminal open on the same project path
![api/slots.php with terminal](screenshots/23_vscode_api_slots_php_with_terminal.png)

---

## 4. The actual data stored in the relational database

To make it obvious the app is backed by a real relational database (not
mock/hardcoded data), the database was seeded with a larger sample set —
**6 users, 12 events, 11 registrations (29 rows total)** — and every row is
shown below via a small local-only data-viewer (`tools/view_data.php`,
not part of the graded app, not linked from any nav menu — it exists purely
so the raw table contents can be screenshotted, the same way you'd use
phpMyAdmin).

### 4.1 Raw contents of `users`, `events`, and `registrations`
![Database raw rows](screenshots/20_browser_database_raw_rows.png)

### 4.2 The public listing rendering all 12 seeded events with live, correct slot math
![All 12 events](screenshots/21_browser_events_full_12.png)

---

## 5. Terminal → Browser → VS Code, working together

This section shows the same feature traced through all three surfaces: the
terminal that runs the app, the browser that uses it, and the VS Code
editor with the source that produced the result.

### 5.1 Terminal — starting PHP's built-in server for the app
![Terminal server start](screenshots/19_terminal_server_start.png)

### 5.2 Browser — hitting the real-time slots JSON API for "Freshers Orientation" (event id 3, 100 slots, 5 registered)
![Browser API JSON](screenshots/22_browser_api_slots_json_2.png)

### 5.3 VS Code — the `api/slots.php` source that produced that exact JSON (see 3.7 above), with the integrated terminal open on the same project
*(same screenshot as 3.7 — included here to complete the terminal → browser → code chain)*

---

## 6. Security Explanation

### 6.1 SQL Injection

Every database query uses **PDO prepared statements with bound parameters**
(`$pdo->prepare(...)` + `execute([...])`). User input is never concatenated
into a SQL string. `PDO::ATTR_EMULATE_PREPARES` is explicitly set to `false`
in `config/database.php`, which forces PHP to send the query and its
parameters to the database **separately** — the database engine keeps code
and data apart, so a malicious string like `' OR '1'='1` is only ever
treated as a literal value, never as SQL syntax. Numeric inputs such as
event IDs are additionally validated with `filter_var(..., FILTER_VALIDATE_INT)`
before use.

### 6.2 Cross-Site Scripting (XSS)

- **Output escaping**: every value that originates from a user or the
  database is passed through the `e()` helper
  (`htmlspecialchars($value, ENT_QUOTES, 'UTF-8')`) before being echoed into
  HTML, converting `<`, `>`, `"` and `'` into harmless entities so an
  injected `<script>` tag renders as visible text, not executable code.
- **Input sanitisation on entry**: `clean_input()` trims and strips tags as
  an additional layer (defense in depth — the real protection is the output
  escaping above).
- **HttpOnly session cookies**: set in `includes/functions.php`, so even if
  an XSS payload executed, JavaScript could not read the session cookie to
  hijack the session.
- **Content-Security-Policy header**: `send_security_headers()` sends a CSP
  restricting scripts to same-origin (`script-src 'self'`), blocking
  inline/injected scripts and scripts from attacker-controlled domains.

### 6.3 Cross-Site Request Forgery (CSRF)

- A **random, unpredictable token** (`bin2hex(random_bytes(32))`) is
  generated once per session and embedded as a hidden field in every
  state-changing form (registration, login, event registration, and all
  admin create/edit/delete actions).
- On every `POST`, `verify_csrf()` checks the submitted token against the
  session's token using `hash_equals()` (timing-attack-resistant) and
  rejects the request with a 403 on mismatch.
- Destructive actions (like deleting an event) are **never performed via a
  plain GET link** — deletion only happens on `POST` with a valid CSRF
  token, so a malicious `<img src="delete_event.php?id=1">` on another site
  cannot trigger it.
- The session cookie is also set with `SameSite=Lax`, stopping it being
  sent on most cross-site requests in the first place.

### 6.4 Other hardening included

- `X-Content-Type-Options: nosniff` and `X-Frame-Options: DENY` headers
  reduce MIME-sniffing and clickjacking risk.
- Generic error messages for login failures ("Invalid email or password")
  so attackers can't enumerate registered emails.
- Simple session-based rate limiting on login attempts to slow brute force.
- Database errors are logged server-side and never shown to the browser.
