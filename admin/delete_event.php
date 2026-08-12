<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = get_db_connection();

$eventId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$eventId) {
    http_response_code(400);
    die('Invalid event.');
}

$stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    http_response_code(404);
    die('Event not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Deletion is a state-changing action, so it MUST go through POST + CSRF
    // token, never a plain GET link, to prevent CSRF-driven deletions.
    verify_csrf();

    $stmt = $pdo->prepare('DELETE FROM events WHERE id = ?');
    $stmt->execute([$eventId]); // registrations cascade-delete via FK

    set_flash('success', 'Event "' . $event['title'] . '" was deleted.');
    redirect('/admin/index.php');
}

$pageTitle = 'Delete Event';
include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <h1>Delete Event</h1>
    <p>Are you sure you want to permanently delete
        <strong><?= e($event['title']) ?></strong>? This will also remove all
        registrations for this event. This action cannot be undone.</p>

    <form method="post" action="/admin/delete_event.php?id=<?= (int)$eventId ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int)$eventId ?>">
        <button type="submit" class="btn-danger">Yes, Delete Event</button>
        <a class="btn btn-secondary" href="/admin/index.php">Cancel</a>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
