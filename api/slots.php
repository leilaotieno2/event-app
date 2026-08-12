<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);

if (!$eventId) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid event id']);
    exit;
}

$pdo = get_db_connection();

// Prepared statement with a bound integer parameter - SQL injection safe.
$stmt = $pdo->prepare(
    "SELECT e.total_slots, e.total_slots - COUNT(r.id) AS slots_remaining
     FROM events e
     LEFT JOIN registrations r ON r.event_id = e.id
     WHERE e.id = ?
     GROUP BY e.id"
);
$stmt->execute([$eventId]);
$row = $stmt->fetch();

if (!$row) {
    http_response_code(404);
    echo json_encode(['error' => 'Event not found']);
    exit;
}

echo json_encode([
    'total_slots'      => (int)$row['total_slots'],
    'slots_remaining'  => max(0, (int)$row['slots_remaining']),
]);
