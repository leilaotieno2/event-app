<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('/index.php');
}

$errors = [];
$email = '';

// Very basic login rate limiting to slow down brute force attempts
if (empty($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['login_attempts_time'] = time();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    if ($_SESSION['login_attempts'] >= 6 && (time() - $_SESSION['login_attempts_time']) < 60) {
        $errors['general'] = 'Too many login attempts. Please wait a minute and try again.';
    } else {
        $email    = clean_input($_POST['email'] ?? '');
        $password = (string)($_POST['password'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }
        if ($password === '') {
            $errors['password'] = 'Please enter your password.';
        }

        if (empty($errors)) {
            $pdo = get_db_connection();
            $stmt = $pdo->prepare('SELECT id, name, password_hash, role FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            // SECURE PASSWORD CHECK: password_verify() against the bcrypt hash
            if ($user && password_verify($password, $user['password_hash'])) {
                // Prevent session fixation attacks
                session_regenerate_id(true);

                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['role']      = $user['role'];
                unset($_SESSION['login_attempts']);

                set_flash('success', 'Welcome back, ' . $user['name'] . '!');
                redirect('/index.php');
            } else {
                $_SESSION['login_attempts']++;
                $_SESSION['login_attempts_time'] = time();
                // Deliberately vague message: don't reveal whether email exists
                $errors['general'] = 'Invalid email or password.';
            }
        }
    }
}

$pageTitle = 'Login';
include __DIR__ . '/includes/header.php';
?>

<div class="card">
    <h1>Log In</h1>
    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-error"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <form class="form-box" method="post" action="/login.php" novalidate>
        <?= csrf_field() ?>

        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" required value="<?= e($email) ?>">
        <?php if (isset($errors['email'])): ?><div class="error-text"><?= e($errors['email']) ?></div><?php endif; ?>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required>
        <?php if (isset($errors['password'])): ?><div class="error-text"><?= e($errors['password']) ?></div><?php endif; ?>

        <button type="submit">Log In</button>
    </form>
    <p>Don't have an account? <a href="/register.php">Register here</a></p>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
