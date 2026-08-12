<?php
/**
 * Generates a downloadable .ics file for a single event so attendees
 * can add it to Google Calendar, Apple Calendar, Outlook, etc.
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$eventId = filter_input(INPUT_GET, 'event_id', FILTER_VALIDATE_INT);
if (!$eventId) {
    http_response_code(400);
    die('Invalid event id');
}

$pdo = get_db_connection();
$stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
$stmt->execute([$eventId]);
$event = $stmt->fetch();

if (!$event) {
    http_response_code(404);
    die('Event not found');
}

function ics_escape(string $text): string
{
    return str_replace(["\\", ",", ";", "\n"], ["\\\\", "\\,", "\\;", "\\n"], $text);
}

$start = new DateTime($event['event_date']);
$end = (clone $start)->modify('+2 hours');
$uid = 'event-' . $event['id'] . '@eventreg.local';
$now = (new DateTime('now', new DateTimeZone('UTC')))->format('Ymd\THis\Z');

$ics = "BEGIN:VCALENDAR\r\n";
$ics .= "VERSION:2.0\r\n";
$ics .= "PRODID:-//EventReg//EN\r\n";
$ics .= "BEGIN:VEVENT\r\n";
$ics .= "UID:$uid\r\n";
$ics .= "DTSTAMP:$now\r\n";
$ics .= 'DTSTART:' . $start->format('Ymd\THis') . "\r\n";
$ics .= 'DTEND:' . $end->format('Ymd\THis') . "\r\n";
$ics .= 'SUMMARY:' . ics_escape($event['title']) . "\r\n";
$ics .= 'DESCRIPTION:' . ics_escape($event['description'] ?? '') . "\r\n";
$ics .= 'LOCATION:' . ics_escape($event['location'] ?? '') . "\r\n";
$ics .= "END:VEVENT\r\n";
$ics .= "END:VCALENDAR\r\n";

$filename = preg_replace('/[^A-Za-z0-9_-]+/', '-', $event['title']) . '.ics';

header('Content-Type: text/calendar; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
echo $ics;
