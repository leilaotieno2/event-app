<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = get_db_connection();

$totalEvents = (int)$pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
$totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
$totalRegistrations = (int)$pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn();
$totalWaitlisted = (int)$pdo->query('SELECT COUNT(*) FROM waitlist')->fetchColumn();
$totalCheckedIn = (int)$pdo->query('SELECT COUNT(*) FROM registrations WHERE checked_in_at IS NOT NULL')->fetchColumn();

$attendanceRate = $totalRegistrations > 0 ? round(100 * $totalCheckedIn / $totalRegistrations) : 0;

// Fill rate per event (top 8 by fill %) - shows organizers what's popular.
$stmt = $pdo->query(
    "SELECT e.title, e.total_slots, COUNT(r.id) AS taken
     FROM events e LEFT JOIN registrations r ON r.event_id = e.id
     GROUP BY e.id
     ORDER BY (COUNT(r.id) * 1.0 / e.total_slots) DESC
     LIMIT 8"
);
$fillRates = $stmt->fetchAll();

// Registrations by category.
$stmt = $pdo->query(
    "SELECT e.category, COUNT(r.id) AS regs
     FROM events e LEFT JOIN registrations r ON r.event_id = e.id
     GROUP BY e.category
     ORDER BY regs DESC"
);
$byCategory = $stmt->fetchAll();
$maxCategoryRegs = max(1, ...array_column($byCategory, 'regs'));

$pageTitle = 'Analytics';
include __DIR__ . '/../includes/header.php';
?>

<h1>Analytics</h1>
<p class="section-sub">A live snapshot of platform activity across all events.</p>

<div class="stat-grid">
    <div class="stat-card"><span class="num"><?= $totalEvents ?></span><br><span class="label">Total events</span></div>
    <div class="stat-card"><span class="num"><?= $totalUsers ?></span><br><span class="label">Registered users</span></div>
    <div class="stat-card"><span class="num"><?= $totalRegistrations ?></span><br><span class="label">Total registrations</span></div>
    <div class="stat-card"><span class="num"><?= $totalWaitlisted ?></span><br><span class="label">On waitlists</span></div>
    <div class="stat-card"><span class="num"><?= $attendanceRate ?>%</span><br><span class="label">Check-in rate</span></div>
</div>

<h2 class="section-title">Fill rate by event</h2>
<div class="card">
    <?php if (empty($fillRates)): ?>
        <p>No events yet.</p>
    <?php endif; ?>
    <?php foreach ($fillRates as $row): ?>
        <?php $pct = $row['total_slots'] > 0 ? min(100, round(100 * $row['taken'] / $row['total_slots'])) : 0; ?>
        <div class="bar-row">
            <div class="bar-label" title="<?= e($row['title']) ?>"><?= e(mb_strimwidth($row['title'], 0, 22, '...')) ?></div>
            <div class="bar-track"><div class="bar-fill" style="width: <?= $pct ?>%;"></div></div>
            <div class="bar-val"><?= (int)$row['taken'] ?>/<?= (int)$row['total_slots'] ?> (<?= $pct ?>%)</div>
        </div>
    <?php endforeach; ?>
</div>

<h2 class="section-title">Registrations by category</h2>
<div class="card">
    <?php foreach ($byCategory as $row): ?>
        <?php $pct = round(100 * $row['regs'] / $maxCategoryRegs); ?>
        <div class="bar-row">
            <div class="bar-label"><?= e($row['category']) ?></div>
            <div class="bar-track"><div class="bar-fill" style="width: <?= $pct ?>%;"></div></div>
            <div class="bar-val"><?= (int)$row['regs'] ?></div>
        </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
