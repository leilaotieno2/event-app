<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/ai.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$pdo = get_db_connection();

$categories = ['General', 'Technology', 'Careers', 'Community', 'Health', 'Business', 'Culture', 'Sports'];
$search = clean_input($_GET['q'] ?? '');
$categoryFilter = clean_input($_GET['category'] ?? '');
if (!in_array($categoryFilter, $categories, true)) {
    $categoryFilter = '';
}

$where = [];
$params = [];
if ($search !== '') {
    $where[] = '(e.title LIKE ? OR e.location LIKE ? OR e.description LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like);
}
if ($categoryFilter !== '') {
    $where[] = 'e.category = ?';
    $params[] = $categoryFilter;
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmt = $pdo->prepare(
    "SELECT e.id, e.title, e.description, e.location, e.category, e.event_date, e.total_slots,
            e.total_slots - COUNT(r.id) AS slots_remaining
     FROM events e
     LEFT JOIN registrations r ON r.event_id = e.id
     $whereSql
     GROUP BY e.id
     ORDER BY e.event_date ASC"
);
$stmt->execute($params);
$events = $stmt->fetchAll();

// Headline stats for the hero banner.
$totalEvents = (int)$pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
$totalRegistrations = (int)$pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn();
$upcomingCount = (int)$pdo->query("SELECT COUNT(*) FROM events WHERE event_date >= CURRENT_TIMESTAMP")->fetchColumn();

// AI-powered "Recommended for you" - only for logged-in users with history.
$recommendations = [];
if (is_logged_in()) {
    $recommendations = ai_recommend_events($pdo, current_user_id(), 3);
}

$pageTitle = 'Upcoming Events';
include __DIR__ . '/includes/header.php';
?>

<div class="hero">
    <div class="hero-inner">
        <h1>Find your next event.</h1>
        <p class="lead">Register in seconds, get real-time availability, and let our AI assistant answer your questions on the spot.</p>
        <div class="hero-stats">
            <div class="hero-stat"><span class="num"><?= $upcomingCount ?></span><span class="label">Upcoming events</span></div>
            <div class="hero-stat"><span class="num"><?= $totalRegistrations ?></span><span class="label">Registrations</span></div>
            <div class="hero-stat"><span class="num"><?= $totalEvents ?></span><span class="label">Events hosted</span></div>
        </div>

        <form class="searchbar" method="get" action="/index.php">
            <input type="search" name="q" placeholder="Search events, locations…" value="<?= e($search) ?>">
            <select name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $c): ?>
                    <option value="<?= e($c) ?>" <?= $c === $categoryFilter ? 'selected' : '' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Search</button>
        </form>
    </div>
</div>

<?php if ($recommendations): ?>
<h2 class="section-title">✨ Recommended for you</h2>
<p class="section-sub">Based on the categories you've registered for before.</p>
<div class="event-grid">
<?php foreach ($recommendations as $rec): ?>
    <div class="event-card">
        <span class="cat-tag"><?= e($rec['category']) ?></span>
        <h3><a href="/event.php?id=<?= (int)$rec['id'] ?>"><?= e($rec['title']) ?></a></h3>
        <p class="meta"><?= e(date('d M Y, H:i', strtotime($rec['event_date']))) ?></p>
        <div class="card-foot">
            <span class="badge-ai">AI Pick</span>
            <a class="btn btn-sm" href="/event.php?id=<?= (int)$rec['id'] ?>">View</a>
        </div>
    </div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<h2 class="section-title">
    <?= $search !== '' || $categoryFilter !== '' ? 'Search Results' : 'All Events' ?>
</h2>
<?php if ($categoryFilter !== ''): ?>
<div class="chip-row">
    <a class="chip" href="/index.php<?= $search !== '' ? '?q=' . urlencode($search) : '' ?>">All</a>
    <?php foreach ($categories as $c): ?>
        <a class="chip <?= $c === $categoryFilter ? 'active' : '' ?>"
           href="/index.php?category=<?= urlencode($c) ?><?= $search !== '' ? '&q=' . urlencode($search) : '' ?>"><?= e($c) ?></a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="event-grid">
<?php if (empty($events)): ?>
    <p>No events match your search. <a href="/index.php">Clear filters</a>.</p>
<?php endif; ?>

<?php foreach ($events as $event): ?>
    <div class="event-card">
        <span class="cat-tag"><?= e($event['category']) ?></span>
        <h3><a href="/event.php?id=<?= (int)$event['id'] ?>"><?= e($event['title']) ?></a></h3>
        <p class="desc"><?= e(mb_strimwidth($event['description'] ?? '', 0, 110, '...')) ?></p>
        <p class="meta"><strong>Date:</strong> <?= e(date('d M Y, H:i', strtotime($event['event_date']))) ?></p>
        <p class="meta"><strong>Location:</strong> <?= e($event['location'] ?? 'TBA') ?></p>
        <div class="card-foot">
            <span class="slots-badge"
                  id="slots-<?= (int)$event['id'] ?>"
                  data-event-id="<?= (int)$event['id'] ?>">
                <?= max(0, (int)$event['slots_remaining']) ?> / <?= (int)$event['total_slots'] ?>
            </span>
            <a class="btn btn-sm" href="/event.php?id=<?= (int)$event['id'] ?>">View / Register</a>
        </div>
    </div>
<?php endforeach; ?>
</div>

<script nonce="<?= e(csp_nonce()) ?>">
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
