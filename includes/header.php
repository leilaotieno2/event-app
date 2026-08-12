<?php
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';
send_security_headers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($pageTitle) ? e($pageTitle) . ' - ' : '' ?>Event Registration System</title>
<link rel="stylesheet" href="/css/style.css">
</head>
<body>
<header class="site-header">
    <a href="/index.php" class="brand">EventReg</a>
    <nav>
        <a href="/index.php">Events</a>
        <?php if (is_admin()): ?>
            <a href="/admin/index.php">Admin Dashboard</a>
        <?php endif; ?>
        <?php if (is_logged_in()): ?>
            <span class="user-pill">Hi, <?= e($_SESSION['user_name'] ?? 'User') ?></span>
            <a href="/logout.php">Logout</a>
        <?php else: ?>
            <a href="/login.php">Login</a>
            <a href="/register.php">Register</a>
        <?php endif; ?>
    </nav>
</header>
<main class="container">
<?php foreach (get_flashes() as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>
