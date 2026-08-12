<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = get_db_connection();

// Prepared statement - safe from SQL injection. No user input here,
// but we keep the same prepared-statement discipline throughout the app.
$stmt = $pdo->query(
    "SELECT e.id, e.title, e.description, e.location, e.event_date, e.total_slots,
            e.total_slots - COUNT(r.id) AS slots_remaining
     FROM events e
     LEFT JOIN registrations r ON r.event_id = e.id
     GROUP BY e.id
     ORDER BY e.event_date ASC"
);
$events = $stmt->fetchAll();

$pageTitle = 'Upcoming Events';
include __DIR__ . '/includes/header.php';
?>

<h1>Upcoming Events</h1>

<div class="event-grid">
<?php if (empty($events)): ?>
    <p>No events have been scheduled yet.</p>
<?php endif; ?>

<?php foreach ($events as $event): ?>
    <div class="card">
        <h3><a href="/event.php?id=<?= (int)$event['id'] ?>"><?= e($event['title']) ?></a></h3>
        <p><?= e(mb_strimwidth($event['description'] ?? '', 0, 120, '...')) ?></p>
        <p><strong>Date:</strong> <?= e(date('d M Y, H:i', strtotime($event['event_date']))) ?></p>
        <p><strong>Location:</strong> <?= e($event['location'] ?? 'TBA') ?></p>
        <p>
            Slots remaining:
            <span class="slots-badge"
                  id="slots-<?= (int)$event['id'] ?>"
                  data-event-id="<?= (int)$event['id'] ?>">
                <?= (int)$event['slots_remaining'] ?> / <?= (int)$event['total_slots'] ?>
            </span>
        </p>
        <a class="btn" href="/event.php?id=<?= (int)$event['id'] ?>">View / Register</a>
    </div>
<?php endforeach; ?>
</div>

<script>
// REAL-TIME SLOT UPDATES
// Polls the JSON API every 5 seconds and refreshes each badge in place,
// so users see live availability without reloading the page.
function refreshSlots() {
    document.querySelectorAll('[data-event-id]').forEach(function (badge) {
        var id = badge.getAttribute('data-event-id');
        fetch('/api/slots.php?event_id=' + encodeURIComponent(id))
            .then(function (res) { return res.ok ? res.json() : Promise.reject(); })
            .then(function (data) {
                badge.textContent = data.slots_remaining + ' / ' + data.total_slots;
                badge.classList.remove('full', 'low');
                if (data.slots_remaining <= 0) {
                    badge.classList.add('full');
                } else if (data.slots_remaining <= 5) {
                    badge.classList.add('low');
                }
            })
            .catch(function () { /* silently ignore transient errors */ });
    });
}
setInterval(refreshSlots, 5000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
