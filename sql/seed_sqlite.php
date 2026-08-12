<?php
// One-off helper: builds sql/event_registration.sqlite from schema_sqlite.sql
// and seeds sample users/events/registrations. Local-testing only.

$dbPath = __DIR__ . '/event_registration.sqlite';
if (file_exists($dbPath)) {
    unlink($dbPath);
}

$pdo = new PDO('sqlite:' . $dbPath);
$pdo->exec('PRAGMA foreign_keys = ON');

$schema = file_get_contents(__DIR__ . '/schema_sqlite.sql');
$pdo->exec($schema);

$insertUser = $pdo->prepare('INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, ?)');

$insertUser->execute(['System Admin', 'admin@example.com', password_hash('Admin@1234', PASSWORD_BCRYPT), 'admin']);
$insertUser->execute(['Jane Student', 'jane@example.com', password_hash('Passw0rd!', PASSWORD_BCRYPT), 'user']);
$insertUser->execute(['Brian Otieno', 'brian@example.com', password_hash('Passw0rd!', PASSWORD_BCRYPT), 'user']);
$insertUser->execute(['Mercy Wanjiru', 'mercy@example.com', password_hash('Passw0rd!', PASSWORD_BCRYPT), 'user']);
$insertUser->execute(['Kevin Mwangi', 'kevin@example.com', password_hash('Passw0rd!', PASSWORD_BCRYPT), 'user']);
$insertUser->execute(['Amina Yusuf', 'amina@example.com', password_hash('Passw0rd!', PASSWORD_BCRYPT), 'user']);

$insertEvent = $pdo->prepare('INSERT INTO events (title, description, location, category, event_date, total_slots, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)');

$events = [
    ['Annual Tech Symposium', 'A community symposium on emerging technology.', 'Main Auditorium', 'Technology', '2026-08-15 09:00:00', 5],
    ['Career Fair 2026', 'Meet employers from academia and industry.', 'Sports Hall', 'Careers', '2026-09-01 10:00:00', 2],
    ['Freshers Orientation', 'Welcome session for new students.', 'Main Hall', 'Community', '2026-08-05 08:30:00', 100],
    ['AI & Robotics Workshop', 'Hands-on workshop on AI and robotics basics.', 'Lab 3', 'Technology', '2026-08-20 13:00:00', 20],
    ['Community Blood Drive', 'Donate blood and save lives.', 'Health Center', 'Health', '2026-08-10 09:00:00', 40],
    ['Entrepreneurship Bootcamp', 'A two-day bootcamp on starting a business.', 'Innovation Hub', 'Business', '2026-09-10 09:00:00', 15],
    ['Cultural Night', 'Celebrating community diversity through music and food.', 'Open Grounds', 'Culture', '2026-09-20 18:00:00', 200],
    ['Cybersecurity Awareness Talk', 'Guest lecture on staying safe online.', 'Lecture Theatre 2', 'Technology', '2026-08-25 11:00:00', 60],
    ['Environmental Clean-up Drive', 'Community clean-up and tree planting.', 'Riverside Park', 'Community', '2026-09-05 07:00:00', 50],
    ['Alumni Networking Mixer', 'Reconnect with alumni across industries.', 'Conference Hall', 'Careers', '2026-09-15 17:00:00', 30],
    ['Public Speaking Masterclass', 'Improve confidence and communication skills.', 'Room 204', 'Business', '2026-08-28 14:00:00', 25],
    ['Sports Gala', 'Inter-department sports competitions.', 'Sports Complex', 'Sports', '2026-09-25 08:00:00', 150],
];
foreach ($events as $e) {
    $insertEvent->execute([$e[0], $e[1], $e[2], $e[3], $e[4], $e[5], 1]);
}

$insertReg = $pdo->prepare('INSERT INTO registrations (user_id, event_id, checkin_code) VALUES (?, ?, ?)');
// user_id 2 = Jane, 3 = Brian, 4 = Mercy, 5 = Kevin, 6 = Amina
$registrations = [
    [2, 1], [3, 1],           // Annual Tech Symposium: 2 taken
    [4, 2],                    // Career Fair 2026: 1 taken
    [2, 3], [3, 3], [4, 3], [5, 3], [6, 3], // Freshers Orientation: 5 taken
    [3, 4], [5, 4],            // AI & Robotics Workshop: 2 taken
    [2, 6],                    // Entrepreneurship Bootcamp: 1 taken
];
foreach ($registrations as $r) {
    $insertReg->execute([$r[0], $r[1], strtoupper(bin2hex(random_bytes(4)))]);
}

echo "Seeded SQLite DB at $dbPath\n";
echo "Users: " . $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() . "\n";
echo "Events: " . $pdo->query('SELECT COUNT(*) FROM events')->fetchColumn() . "\n";
echo "Registrations: " . $pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn() . "\n";
