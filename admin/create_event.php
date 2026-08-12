<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = get_db_connection();
$errors = [];
$title = $description = $location = $eventDate = '';
$totalSlots = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $title       = clean_input($_POST['title'] ?? '');
    $description = trim(strip_tags($_POST['description'] ?? '')); // allow line breaks, strip HTML
    $location    = clean_input($_POST['location'] ?? '');
    $eventDate   = clean_input($_POST['event_date'] ?? '');
    $totalSlots  = clean_input($_POST['total_slots'] ?? '');

    // ---------------- SERVER-SIDE VALIDATION ----------------
    if ($title === '' || mb_strlen($title) > 150) {
        $errors['title'] = 'Title is required (max 150 characters).';
    }
    $dateObj = DateTime::createFromFormat('Y-m-d\TH:i', $eventDate);
    if (!$dateObj) {
        $errors['event_date'] = 'Please provide a valid date and time.';
    }
    if (!ctype_digit($totalSlots) || (int)$totalSlots < 1 || (int)$totalSlots > 100000) {
        $errors['total_slots'] = 'Total slots must be a whole number of at least 1.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO events (title, description, location, event_date, total_slots, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $title,
            $description,
            $location,
            $dateObj->format('Y-m-d H:i:s'),
            (int)$totalSlots,
            current_user_id(),
        ]);

        set_flash('success', 'Event created successfully.');
        redirect('/admin/index.php');
    }
}

$pageTitle = 'Create Event';
include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <h1>Create New Event</h1>
    <form class="form-box" method="post" action="/admin/create_event.php" novalidate>
        <?= csrf_field() ?>

        <label for="title">Title</label>
        <input type="text" id="title" name="title" required maxlength="150" value="<?= e($title) ?>">
        <?php if (isset($errors['title'])): ?><div class="error-text"><?= e($errors['title']) ?></div><?php endif; ?>

        <label for="description">Description</label>
        <textarea id="description" name="description" rows="5"><?= e($description) ?></textarea>

        <label for="location">Location</label>
        <input type="text" id="location" name="location" maxlength="150" value="<?= e($location) ?>">

        <label for="event_date">Date &amp; Time</label>
        <input type="datetime-local" id="event_date" name="event_date" required value="<?= e($eventDate) ?>">
        <?php if (isset($errors['event_date'])): ?><div class="error-text"><?= e($errors['event_date']) ?></div><?php endif; ?>

        <label for="total_slots">Total Slots</label>
        <input type="number" id="total_slots" name="total_slots" required min="1" step="1" value="<?= e($totalSlots) ?>">
        <?php if (isset($errors['total_slots'])): ?><div class="error-text"><?= e($errors['total_slots']) ?></div><?php endif; ?>

        <button type="submit">Create Event</button>
    </form>
    <p><a href="/admin/index.php">&larr; Back to dashboard</a></p>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
