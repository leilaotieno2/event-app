<?php
require_once __DIR__ . '/functions.php';

function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

function is_admin(): bool
{
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

function current_user_id(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to continue.');
        redirect('/login.php');
    }
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        http_response_code(403);
        die('Access denied: administrator privileges are required.');
    }
}
