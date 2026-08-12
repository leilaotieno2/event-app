<?php
/**
 * LOCAL DEMO/DOCUMENTATION TOOL ONLY.
 * Not part of the graded application, not linked from any nav menu.
 * Just dumps raw table contents so screenshots can show what is
 * actually stored in the relational database (like phpMyAdmin would).
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

$pdo = get_db_connection();

function dump_table(PDO $pdo, string $table): array
{
    return $pdo->query("SELECT * FROM $table ORDER BY id")->fetchAll();
}

$users = dump_table($pdo, 'users');
$events = dump_table($pdo, 'events');
$registrations = dump_table($pdo, 'registrations');
?>
<!doctype html>
<html>
<head>
<meta charset="utf-8">
<title>Database Contents (local demo tool)</title>
<style>
body { font-family: Arial, sans-serif; margin: 24px; background: #f4f5f7; color: #1e293b; }
h1 { margin-bottom: 4px; }
h2 { margin-top: 32px; border-bottom: 2px solid #2b3a55; padding-bottom: 4px; }
table { border-collapse: collapse; width: 100%; background: #fff; margin-top: 8px; }
th, td { border: 1px solid #d1d5db; padding: 6px 10px; font-size: 13px; text-align: left; }
th { background: #1e293b; color: #fff; }
tr:nth-child(even) { background: #f9fafb; }
.count { color: #475569; font-weight: bold; }
</style>
</head>
<body>
<h1>Database Contents — <?= e(DB_NAME ?? 'event_registration') ?></h1>
<p>Local documentation view only (<?= (getenv('DB_DRIVER') ?: 'mysql') ?> driver). Shows every row currently stored.</p>

<h2>users <span class="count">(<?= count($users) ?> rows)</span></h2>
<table>
<tr><th>id</th><th>name</th><th>email</th><th>password_hash (bcrypt)</th><th>role</th><th>created_at</th></tr>
<?php foreach ($users as $u): ?>
<tr>
<td><?= e($u['id']) ?></td>
<td><?= e($u['name']) ?></td>
<td><?= e($u['email']) ?></td>
<td><?= e(substr($u['password_hash'], 0, 30)) ?>...</td>
<td><?= e($u['role']) ?></td>
<td><?= e($u['created_at']) ?></td>
</tr>
<?php endforeach; ?>
</table>

<h2>events <span class="count">(<?= count($events) ?> rows)</span></h2>
<table>
<tr><th>id</th><th>title</th><th>location</th><th>event_date</th><th>total_slots</th><th>created_by</th></tr>
<?php foreach ($events as $ev): ?>
<tr>
<td><?= e($ev['id']) ?></td>
<td><?= e($ev['title']) ?></td>
<td><?= e($ev['location']) ?></td>
<td><?= e($ev['event_date']) ?></td>
<td><?= e($ev['total_slots']) ?></td>
<td><?= e($ev['created_by']) ?></td>
</tr>
<?php endforeach; ?>
</table>

<h2>registrations <span class="count">(<?= count($registrations) ?> rows)</span></h2>
<table>
<tr><th>id</th><th>user_id</th><th>event_id</th><th>registered_at</th></tr>
<?php foreach ($registrations as $r): ?>
<tr>
<td><?= e($r['id']) ?></td>
<td><?= e($r['user_id']) ?></td>
<td><?= e($r['event_id']) ?></td>
<td><?= e($r['registered_at']) ?></td>
</tr>
<?php endforeach; ?>
</table>

<p style="margin-top:32px;color:#64748b;font-size:12px;">
Total rows across all tables: <?= count($users) + count($events) + count($registrations) ?>
</p>
</body>
</html>
