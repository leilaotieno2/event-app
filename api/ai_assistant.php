<?php
/**
 * Public endpoint powering the "Ask AI" widget on event.php.
 * Answers attendee questions using only that event's own data
 * (see ai_answer_question() for the real-AI vs stub behaviour).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ai.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$token = $input['csrf_token'] ?? '';
if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token']);
    exit;
}

// Light per-session rate limit so this can't be hammered.
$_SESSION['ai_asks'] = $_SESSION['ai_asks'] ?? [];
$_SESSION['ai_asks'] = array_filter($_SESSION['ai_asks'], fn($t) => $t > time() - 60);
if (count($_SESSION['ai_asks']) >= 15) {
    http_response_code(429);
    echo json_encode(['error' => 'Too many questions - please wait a moment.']);
    exit;
}
$_SESSION['ai_asks'][] = time();

$eventId = filter_var($input['event_id'] ?? null, FILTER_VALIDATE_INT);
$question = trim((string)($input['question'] ?? ''));

if (!$eventId || $question === '' || mb_strlen($question) > 300) {
    http_response_code(400);
    echo json_encode(['error' => 'Please provide a valid question (max 300 characters).']);
    exit;
}

$pdo = get_db_connection();
$stmt = $pdo->prepare(
    "SELECT e.*, e.total_slots - COUNT(r.id) AS slots_remaining
     FROM events e LEFT JOIN registrations r ON r.event_id = e.id
     WHERE e.id = ? GROUP BY e.id"
);
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    http_response_code(404);
    echo json_encode(['error' => 'Event not found']);
    exit;
}

echo json_encode([
    'answer' => ai_answer_question($event, $question),
    'source' => ai_is_live() ? 'ai' : 'stub',
]);
