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

$myRegistration = null;
$onWaitlist = false;
if (is_logged_in()) {
    $stmt = $pdo->prepare('SELECT id, checkin_code, checked_in_at FROM registrations WHERE user_id = ? AND event_id = ?');
    $stmt->execute([current_user_id(), $eventId]);
    $myRegistration = $stmt->fetch() ?: null;

    $stmt = $pdo->prepare('SELECT id FROM waitlist WHERE user_id = ? AND event_id = ?');
    $stmt->execute([current_user_id(), $eventId]);
    $onWaitlist = (bool)$stmt->fetch();
}
$alreadyRegistered = (bool)$myRegistration;

$registerError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_event'])) {
    require_login();
    verify_csrf();

    $postedEventId = filter_input(INPUT_POST, 'event_id', FILTER_VALIDATE_INT);
    if ($postedEventId !== $eventId) {
        http_response_code(400);
        die('Invalid request.');
    }

    // TRANSACTION + ROW LOCK: this closes the race condition where two
    // requests could both read "1 slot left" and both insert, causing
    // over-booking. MySQL: FOR UPDATE locks the event row until we
    // commit. SQLite has no row locking - BEGIN IMMEDIATE takes a write
    // lock on the whole database for the same effect. PDO's own
    // beginTransaction()/commit()/rollBack() don't track a raw "BEGIN
    // IMMEDIATE" issued via exec(), so we use exec() consistently for
    // both drivers to keep PDO's transaction state in sync.
    $isSqlite = DB_DRIVER === 'sqlite';
    $lockClause = $isSqlite ? '' : 'FOR UPDATE';

    try {
        $pdo->exec($isSqlite ? 'BEGIN IMMEDIATE' : 'BEGIN');

        $stmt = $pdo->prepare(
            "SELECT e.total_slots,
                    (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) AS taken
             FROM events e WHERE e.id = ? $lockClause"
        );
        $stmt->execute([$eventId]);
        $locked = $stmt->fetch();

        if (!$locked) {
            throw new RuntimeException('Event no longer exists.');
        }

        if ($locked['taken'] >= $locked['total_slots']) {
            $registerError = 'Sorry, this event is now full. Join the waitlist below to be notified if a spot opens up.';
            $pdo->exec('ROLLBACK');
        } else {
            $dupStmt = $pdo->prepare(
                'SELECT id FROM registrations WHERE user_id = ? AND event_id = ?'
            );
            $dupStmt->execute([current_user_id(), $eventId]);

            if ($dupStmt->fetch()) {
                $registerError = 'You are already registered for this event.';
                $pdo->exec('ROLLBACK');
            } else {
                $checkinCode = strtoupper(bin2hex(random_bytes(4)));
                $insert = $pdo->prepare(
                    'INSERT INTO registrations (user_id, event_id, checkin_code) VALUES (?, ?, ?)'
                );
                $insert->execute([current_user_id(), $eventId, $checkinCode]);
                $pdo->prepare('DELETE FROM waitlist WHERE user_id = ? AND event_id = ?')
                    ->execute([current_user_id(), $eventId]);
                $pdo->exec('COMMIT');

                set_flash('success', 'You are registered for "' . $event['title'] . '"! Your check-in code is ' . $checkinCode . '.');
                redirect('/event.php?id=' . $eventId);
            }
        }
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->exec('ROLLBACK');
        }
        if ($e->getCode() === '23000') {
            $registerError = 'You are already registered for this event.';
        } else {
            error_log('Registration error: ' . $e->getMessage());
            $registerError = 'Something went wrong. Please try again.';
        }
    }

    $event = fetch_event($pdo, $eventId);
    $stmt = $pdo->prepare('SELECT id, checkin_code, checked_in_at FROM registrations WHERE user_id = ? AND event_id = ?');
    $stmt->execute([current_user_id(), $eventId]);
    $myRegistration = $stmt->fetch() ?: null;
    $alreadyRegistered = (bool)$myRegistration || ($registerError === 'You are already registered for this event.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_waitlist'])) {
    require_login();
    verify_csrf();
    try {
        $stmt = $pdo->prepare('INSERT INTO waitlist (user_id, event_id) VALUES (?, ?)');
        $stmt->execute([current_user_id(), $eventId]);
        set_flash('success', "You're on the waitlist. We'll let you know if a spot opens up.");
    } catch (PDOException $e) {
        // Already on the waitlist (UNIQUE constraint) - not an error worth showing.
    }
    redirect('/event.php?id=' . $eventId);
}

$pageTitle = $event['title'];
include __DIR__ . '/includes/header.php';
?>

<div class="card">
    <span class="cat-tag"><?= e($event['category'] ?? 'General') ?></span>
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

    <a class="btn btn-ghost btn-sm" href="/api/calendar.php?event_id=<?= (int)$event['id'] ?>">📅 Add to calendar</a>

    <?php if ($registerError): ?>
        <div class="alert alert-error"><?= e($registerError) ?></div>
    <?php endif; ?>

    <?php if (!is_logged_in()): ?>
        <p style="margin-top:16px;"><a href="/login.php">Log in</a> to register for this event.</p>
    <?php elseif ($alreadyRegistered && $myRegistration): ?>
        <div class="alert alert-success">
            You're registered for this event.
            <?php if ($myRegistration['checked_in_at']): ?>
                Checked in at <?= e(date('d M Y, H:i', strtotime($myRegistration['checked_in_at']))) ?>.
            <?php else: ?>
                Show this code at check-in: <strong><?= e($myRegistration['checkin_code']) ?></strong>
            <?php endif; ?>
        </div>
    <?php elseif ((int)$event['slots_remaining'] <= 0): ?>
        <div class="alert alert-error">This event is full.</div>
        <?php if ($onWaitlist): ?>
            <div class="alert alert-success">You're on the waitlist - we'll notify you if a spot opens up.</div>
        <?php else: ?>
            <form method="post" action="/event.php?id=<?= (int)$event['id'] ?>">
                <?= csrf_field() ?>
                <button type="submit" name="join_waitlist" value="1" class="btn-secondary">Join Waitlist</button>
            </form>
        <?php endif; ?>
    <?php else: ?>
        <form method="post" action="/event.php?id=<?= (int)$event['id'] ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="event_id" value="<?= (int)$event['id'] ?>">
            <button type="submit" name="register_event" value="1">Register for this Event</button>
        </form>
    <?php endif; ?>

    <p><a href="/index.php">&larr; Back to all events</a></p>
</div>

<div class="ai-widget" id="aiWidget">
    <button class="ai-widget-toggle" id="aiToggle">💬 Ask AI about this event</button>
    <div class="ai-widget-panel" id="aiPanel">
        <div class="ai-widget-head">Event Assistant</div>
        <div class="ai-widget-body" id="aiBody">
            <div class="ai-msg bot">Ask me about the date, location, availability, or anything else about this event.</div>
        </div>
        <form class="ai-widget-form" id="aiForm">
            <input type="text" id="aiInput" placeholder="Type a question…" maxlength="300" autocomplete="off">
            <button type="submit">Send</button>
        </form>
    </div>
</div>

<script nonce="<?= e(csp_nonce()) ?>">
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

// AI assistant widget
var aiToggle = document.getElementById('aiToggle');
var aiPanel = document.getElementById('aiPanel');
var aiBody = document.getElementById('aiBody');
var aiForm = document.getElementById('aiForm');
var aiInput = document.getElementById('aiInput');
var csrfToken = '<?= e(csrf_token()) ?>';

aiToggle.addEventListener('click', function () {
    aiPanel.classList.toggle('open');
});

aiForm.addEventListener('submit', function (e) {
    e.preventDefault();
    var question = aiInput.value.trim();
    if (!question) return;

    var userMsg = document.createElement('div');
    userMsg.className = 'ai-msg user';
    userMsg.textContent = question;
    aiBody.appendChild(userMsg);
    aiInput.value = '';
    aiBody.scrollTop = aiBody.scrollHeight;

    fetch('/api/ai_assistant.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ event_id: <?= (int)$event['id'] ?>, question: question, csrf_token: csrfToken })
    })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            var botMsg = document.createElement('div');
            botMsg.className = 'ai-msg bot';
            botMsg.textContent = data.answer || data.error || 'Sorry, something went wrong.';
            aiBody.appendChild(botMsg);
            aiBody.scrollTop = aiBody.scrollHeight;
        })
        .catch(function () {
            var botMsg = document.createElement('div');
            botMsg.className = 'ai-msg bot';
            botMsg.textContent = 'Sorry, I could not reach the assistant right now.';
            aiBody.appendChild(botMsg);
        });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
