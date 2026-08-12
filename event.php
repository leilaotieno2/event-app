<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = get_db_connection();

$eventId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$eventId) {
    http_response_code(400);
    die('Invalid event.');
}

function fetch_event(PDO $pdo, int $eventId): ?array
{
    $stmt = $pdo->prepare(
        "SELECT e.*, e.total_slots - COUNT(r.id) AS slots_remaining
         FROM events e
         LEFT JOIN registrations r ON r.event_id = e.id
         WHERE e.id = ?
         GROUP BY e.id"
    );
    $stmt->execute([$eventId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

$event = fetch_event($pdo, $eventId);
if (!$event) {
    http_response_code(404);
    die('Event not found.');
}

$alreadyRegistered = false;
if (is_logged_in()) {
    $stmt = $pdo->prepare('SELECT id FROM registrations WHERE user_id = ? AND event_id = ?');
    $stmt->execute([current_user_id(), $eventId]);
    $alreadyRegistered = (bool)$stmt->fetch();
}

$registerError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_event'])) {
    require_login();
    verify_csrf();

    // Re-validate the posted event id matches this page (defense in depth)
    $postedEventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
    if ($postedEventId !== $eventId) {
        http_response_code(400);
        die('Invalid request.');
    }

    try {
        // TRANSACTION + ROW LOCK: this closes the race condition where two
        // requests could both read "1 slot left" and both insert, causing
        // over-booking. FOR UPDATE locks the event row until we commit.
        $pdo->beginTransaction();

        $stmt = $pdo->prepare(
            "SELECT e.total_slots,
                    (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) AS taken
             FROM events e WHERE e.id = ? FOR UPDATE"
        );
        $stmt->execute([$eventId]);
        $locked = $stmt->fetch();

        if (!$locked) {
            throw new RuntimeException('Event no longer exists.');
        }

        if ($locked['taken'] >= $locked['total_slots']) {
            $registerError = 'Sorry, this event is now full.';
            $pdo->rollBack();
        } else {
            // DUPLICATE REGISTRATION PREVENTION:
            // 1) UNIQUE(user_id, event_id) constraint in the DB is the real guarantee.
            // 2) We also pre-check here for a friendly error message.
            $dupStmt = $pdo->prepare(
                'SELECT id FROM registrations WHERE user_id = ? AND event_id = ?'
            );
            $dupStmt->execute([current_user_id(), $eventId]);

            if ($dupStmt->fetch()) {
                $registerError = 'You are already registered for this event.';
                $pdo->rollBack();
            } else {
                $insert = $pdo->prepare(
                    'INSERT INTO registrations (user_id, event_id) VALUES (?, ?)'
                );
                $insert->execute([current_user_id(), $eventId]);
                $pdo->commit();

                set_flash('success', 'You are registered for "' . $event['title'] . '"!');
                redirect('/event.php?id=' . $eventId);
            }
        }
    } catch (PDOException $e) {
        $pdo->rollBack();
        // Error code 23000 = integrity constraint violation (our UNIQUE key).
        // This is the final safety net if two requests race past the checks above.
        if ($e->getCode() === '23000') {
            $registerError = 'You are already registered for this event.';
        } else {
            error_log('Registration error: ' . $e->getMessage());
            $registerError = 'Something went wrong. Please try again.';
        }
    }

    // Refresh event data after the attempt
    $event = fetch_event($pdo, $eventId);
    $alreadyRegistered = $alreadyRegistered || ($registerError === 'You are already registered for this event.');
}

$pageTitle = $event['title'];
include __DIR__ . '/includes/header.php';
?>

<div class="card">
    <h1><?= e($event['title']) ?></h1>
    <p><?= nl2br(e($event['description'])) ?></p>
    <p><strong>Date:</strong> <?= e(date('d M Y, H:i', strtotime($event['event_date']))) ?></p>
    <p><strong>Location:</strong> <?= e($event['location'] ?? 'TBA') ?></p>
    <p>
        Slots remaining:
        <span class="slots-badge" id="slots-<?= (int)$event['id'] ?>" data-event-id="<?= (int)$event['id'] ?>">
            <?= max(0, (int)$event['slots_remaining']) ?> / <?= (int)$event['total_slots'] ?>
        </span>
    </p>

    <?php if ($registerError): ?>
        <div class="alert alert-error"><?= e($registerError) ?></div>
    <?php endif; ?>

    <?php if (!is_logged_in()): ?>
        <p><a href="/login.php">Log in</a> to register for this event.</p>
    <?php elseif ($alreadyRegistered): ?>
        <div class="alert alert-success">You are already registered for this event.</div>
    <?php elseif ((int)$event['slots_remaining'] <= 0): ?>
        <div class="alert alert-error">This event is full.</div>
    <?php else: ?>
        <form method="post" action="/event.php?id=<?= (int)$event['id'] ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
            <button type="submit" name="register_event" value="1">Register for this Event</button>
        </form>
    <?php endif; ?>

    <p><a href="/index.php">&larr; Back to all events</a></p>
</div>

<script>
function refreshSlots() {
    var badge = document.getElementById('slots-<?= (int)$event['id'] ?>');
    if (!badge) return;
    fetch('/api/slots.php?event_id=<?= (int)$event['id'] ?>')
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
        .catch(function () {});
}
setInterval(refreshSlots, 5000);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
