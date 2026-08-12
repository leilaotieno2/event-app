<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin(); // only admins may reach this page

$pdo = get_db_connection();
$stmt = $pdo->query(
    "SELECT e.id, e.title, e.event_date, e.total_slots, COUNT(r.id) AS taken
     FROM events e
     LEFT JOIN registrations r ON r.event_id = e.id
     GROUP BY e.id
     ORDER BY e.event_date ASC"
);
$events = $stmt->fetchAll();

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<h1>Admin Dashboard</h1>
<a class="btn" href="/admin/create_event.php">+ Create New Event</a>

<div class="card">
    <table>
        <thead>
            <tr><th>Title</th><th>Date</th><th>Slots (taken/total)</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($events as $ev): ?>
            <tr>
                <td><?= e($ev['title']) ?></td>
                <td><?= e(date('d M Y, H:i', strtotime($ev['event_date']))) ?></td>
                <td><?= (int)$ev['taken'] ?> / <?= (int)$ev['total_slots'] ?></td>
                <td>
                    <a href="/admin/edit_event.php?id=<?= (int)$ev['id'] ?>">Edit</a>
                    &nbsp;|&nbsp;
                    <a href="/admin/delete_event.php?id=<?= (int)$ev['id'] ?>" style="color:#b32424;">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($events)): ?>
            <tr><td colspan="4">No events yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
