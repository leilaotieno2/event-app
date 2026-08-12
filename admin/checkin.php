<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = get_db_connection();
$result = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $code = strtoupper(trim(clean_input($_POST['code'] ?? '')));

    if ($code === '') {
        $error = 'Please enter a check-in code.';
    } else {
        $stmt = $pdo->prepare(
            "SELECT r.id, r.checked_in_at, u.name AS user_name, e.title AS event_title, e.id AS event_id
             FROM registrations r
             JOIN users u ON u.id = r.user_id
             JOIN events e ON e.id = r.event_id
             WHERE r.checkin_code = ?"
        );
        $stmt->execute([$code]);
        $reg = $stmt->fetch();

        if (!$reg) {
            $error = 'No registration found for that code.';
        } elseif ($reg['checked_in_at']) {
            $result = $reg;
            $error = 'This attendee was already checked in at ' . date('d M Y, H:i', strtotime($reg['checked_in_at'])) . '.';
        } else {
            $pdo->prepare('UPDATE registrations SET checked_in_at = CURRENT_TIMESTAMP WHERE id = ?')
                ->execute([$reg['id']]);
            $reg['checked_in_at'] = date('Y-m-d H:i:s');
            $result = $reg;
        }
    }
}

$pageTitle = 'Check-in';
include __DIR__ . '/../includes/header.php';
?>

<h1>Attendee Check-in</h1>
<p class="section-sub">Enter the code an attendee shows you at the door to check them in.</p>

<div class="card">
    <form class="form-box" method="post" action="/admin/checkin.php">
        <?= csrf_field() ?>
        <label for="code">Check-in Code</label>
        <input type="text" id="code" name="code" maxlength="20" autofocus placeholder="e.g. A1B2C3D4" style="text-transform:uppercase;">
        <button type="submit">Check In</button>
    </form>

    <?php if ($error): ?>
        <div class="alert alert-error" style="margin-top:16px;"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($result && !$error): ?>
        <div class="alert alert-success" style="margin-top:16px;">
            ✅ <strong><?= e($result['user_name']) ?></strong> checked in for
            <strong><?= e($result['event_title']) ?></strong>.
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
