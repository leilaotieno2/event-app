<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin(); // only admins may reach this page

$pdo = get_db_connection();
$stmt = $pdo->query(
    "SELECT e.id, e.title, e.event_date, e.total_slots, COUNT(DISTINCT r.id) AS taken, COUNT(DISTINCT w.id) AS waitlisted
     FROM events e
     LEFT JOIN registrations r ON r.event_id = e.id
     LEFT JOIN waitlist w ON w.event_id = e.id
     GROUP BY e.id
     ORDER BY e.event_date ASC"
);
$events = $stmt->fetchAll();

$pageTitle = 'Admin Dashboard';
include __DIR__ . '/../includes/header.php';
?>

<h1>Admin Dashboard</h1>
<a class="btn" href="/admin/create_event.php">+ Create New Event</a>
<a class="btn btn-ghost" href="/admin/checkin.php">Check-in Attendees</a>
<a class="btn btn-ghost" href="/admin/analytics.php">View Analytics</a>

<div class="card">
    <table>
        <thead>
            <tr><th>Title</th><th>Date</th><th>Slots (taken/total)</th><th>Waitlist</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($events as $ev): ?>
            <tr>
                <td><?= e($ev['title']) ?></td>
                <td><?= e(date('d M Y, H:i', strtotime($ev['event_date']))) ?></td>
                <td><?= (int)$ev['taken'] ?> / <?= (int)$ev['total_slots'] ?></td>
                <td><?= (int)$ev['waitlisted'] ?></td>
                <td>
                    <a href="/admin/edit_event.php?id=<?= (int)$ev['id'] ?>">Edit</a>
                    &nbsp;|&nbsp;
                    <a href="/admin/delete_event.php?id=<?= (int)$ev['id'] ?>" style="color:#b32424;">Delete</a>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($events)): ?>
            <tr><td colspan="5">No events yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
