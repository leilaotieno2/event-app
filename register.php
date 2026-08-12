<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('/index.php');
}

$errors = [];
$name = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $name            = clean_input($_POST['name'] ?? '');
    $email           = clean_input($_POST['email'] ?? '');
    $password        = (string)($_POST['password'] ?? '');
    $confirmPassword = (string)($_POST['confirm_password'] ?? '');

    // ---------------- SERVER-SIDE VALIDATION ----------------
    if ($name === '' || mb_strlen($name) > 100) {
        $errors['name'] = 'Please enter your full name (max 100 characters).';
    }
    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $errors['password'] = 'Password must be at least 8 characters and include an uppercase letter and a number.';
    }
    if ($password !== $confirmPassword) {
        $errors['confirm_password'] = 'Passwords do not match.';
    }

    if (empty($errors)) {
        $pdo = get_db_connection();

        // Check email uniqueness (prepared statement -> SQL injection safe)
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors['email'] = 'An account with this email already exists.';
        }
    }

    if (empty($errors)) {
        // SECURE PASSWORD HASHING: bcrypt via password_hash(). Never store plain text.
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$name, $email, $hash, 'user']);

        set_flash('success', 'Registration successful! Please log in.');
        redirect('/login.php');
    }
}

$pageTitle = 'Register';
include __DIR__ . '/includes/header.php';
?>

<div class="card">
    <h1>Create an Account</h1>
    <form class="form-box" method="post" action="/register.php" novalidate id="registerForm">
        <?= csrf_field() ?>

        <label for="name">Full Name</label>
        <input type="text" id="name" name="name" required maxlength="100"
               value="<?= e($name) ?>">
        <?php if (isset($errors['name'])): ?><div class="error-text"><?= e($errors['name']) ?></div><?php endif; ?>

        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" required maxlength="150"
               value="<?= e($email) ?>">
        <?php if (isset($errors['email'])): ?><div class="error-text"><?= e($errors['email']) ?></div><?php endif; ?>

        <label for="password">Password</label>
        <input type="password" id="password" name="password" required minlength="8"
               pattern="(?=.*[A-Z])(?=.*[0-9]).{8,}">
        <div class="hint">At least 8 characters, one uppercase letter and one number.</div>
        <?php if (isset($errors['password'])): ?><div class="error-text"><?= e($errors['password']) ?></div><?php endif; ?>

        <label for="confirm_password">Confirm Password</label>
        <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
        <?php if (isset($errors['confirm_password'])): ?><div class="error-text"><?= e($errors['confirm_password']) ?></div><?php endif; ?>
        <div id="matchError" class="error-text" style="display:none;">Passwords do not match.</div>

        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="/login.php">Log in</a></p>
</div>

<script nonce="<?= e(csp_nonce()) ?>">
// CLIENT-SIDE VALIDATION (defense in depth - server always re-validates)
document.getElementById('registerForm').addEventListener('submit', function (e) {
    var pw = document.getElementById('password').value;
    var cpw = document.getElementById('confirm_password').value;
    var matchError = document.getElementById('matchError');
    if (pw !== cpw) {
        e.preventDefault();
        matchError.style.display = 'block';
    } else {
        matchError.style.display = 'none';
    }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
