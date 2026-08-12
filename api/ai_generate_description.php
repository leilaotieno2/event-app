<?php
/**
 * Admin-only endpoint: generate a draft event description from a
 * few fields, for the "Generate with AI" button on the create/edit
 * event forms. POST + CSRF protected like every other state-adjacent
 * action in the app, even though it doesn't write to the database.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/ai.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!is_admin()) {
    http_response_code(403);
    echo json_encode(['error' => 'Admin access required']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];
$token = $input['csrf_token'] ?? '';
if (!is_string($token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token']);
    exit;
}

$title    = clean_input($input['title'] ?? '');
$category = clean_input($input['category'] ?? 'General');
$location = clean_input($input['location'] ?? '');
$notes    = clean_input($input['notes'] ?? '');
$eventDate = clean_input($input['event_date'] ?? '');

if ($title === '') {
    http_response_code(400);
    echo json_encode(['error' => 'A title is required before generating a description.']);
    exit;
}

$dateHuman = 'a date to be confirmed';
$dateObj = DateTime::createFromFormat('Y-m-d\TH:i', $eventDate);
if ($dateObj) {
    $dateHuman = $dateObj->format('d M Y \a\t H:i');
}

$description = ai_generate_description($title, $category, $location, $dateHuman, $notes);

echo json_encode([
    'description' => $description,
    'source' => ai_is_live() ? 'ai' : 'stub',
]);
