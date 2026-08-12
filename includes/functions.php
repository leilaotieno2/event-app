<?php
/**
 * Shared helper functions used across the whole application.
 */

if (session_status() === PHP_SESSION_NONE) {
    // Harden session cookie
    session_set_cookie_params([
        'httponly' => true,   // JS cannot read the session cookie (helps vs XSS session theft)
        'samesite' => 'Lax',  // helps mitigate CSRF on top of our token defense
        'secure'   => isset($_SERVER['HTTPS']), // only send over HTTPS when available
    ]);
    session_start();
}

/**
 * Trim + strip tags on every piece of user input before it
 * ever touches business logic. Output escaping (htmlspecialchars)
 * still happens again at display time - defense in depth.
 */
function clean_input(string $data): string
{
    $data = trim($data);
    $data = stripslashes($data);
    $data = strip_tags($data);
    return $data;
}

/**
 * Escape a string for safe HTML output.
 * XSS DEFENSE: every single value that came from a user or the
 * database is passed through this function before being echoed
 * into a page.
 */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

/* ---------------------------------------------------------------
 * CSRF PROTECTION
 * A random token is generated once per session and embedded as a
 * hidden field in every state-changing form (register, login,
 * event registration, admin create/edit/delete). On submission the
 * token is compared with hash_equals() (timing-safe) before any
 * action is taken. Requests with a missing/incorrect token are
 * rejected outright.
 * ------------------------------------------------------------- */
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
        http_response_code(403);
        die('Invalid or expired security token. Please go back, refresh the page, and try again.');
    }
}

/* ---------------------------------------------------------------
 * Flash messages (one-time success/error notices shown after redirect)
 * ------------------------------------------------------------- */
function set_flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function redirect(string $path): void
{
    header('Location: ' . $path);
    exit;
}

/* ---------------------------------------------------------------
 * Basic security headers applied to every page.
 * ------------------------------------------------------------- */
function send_security_headers(): void
{
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
    header("Referrer-Policy: strict-origin-when-cross-origin");
    // A conservative CSP: only allow same-origin scripts/styles.
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'");
}
