<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$pdo = get_db_connection();
$errors = [];
$title = $description = $location = $eventDate = '';
$category = 'General';
$totalSlots = '';
$categories = ['General', 'Technology', 'Careers', 'Community', 'Health', 'Business', 'Culture', 'Sports'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $title       = clean_input($_POST['title'] ?? '');
    $description = trim(strip_tags($_POST['description'] ?? '')); // allow line breaks, strip HTML
    $location    = clean_input($_POST['location'] ?? '');
    $category    = clean_input($_POST['category'] ?? 'General');
    $eventDate   = clean_input($_POST['event_date'] ?? '');
    $totalSlots  = clean_input($_POST['total_slots'] ?? '');

    // ---------------- SERVER-SIDE VALIDATION ----------------
    if ($title === '' || mb_strlen($title) > 150) {
        $errors['title'] = 'Title is required (max 150 characters).';
    }
    if (!in_array($category, $categories, true)) {
        $category = 'General';
    }
    $dateObj = DateTime::createFromFormat('Y-m-d\TH:i', $eventDate);
    if (!$dateObj) {
        $errors['event_date'] = 'Please provide a valid date and time.';
    }
    if (!ctype_digit($totalSlots) || (int)$totalSlots < 1 || (int)$totalSlots > 100000) {
        $errors['total_slots'] = 'Total slots must be a whole number of at least 1.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            'INSERT INTO events (title, description, location, category, event_date, total_slots, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $title,
            $description,
            $location,
            $category,
            $dateObj->format('Y-m-d H:i:s'),
            (int)$totalSlots,
            current_user_id(),
        ]);

        set_flash('success', 'Event created successfully.');
        redirect('/admin/index.php');
    }
}

$pageTitle = 'Create Event';
include __DIR__ . '/../includes/header.php';
?>

<div class="card">
    <h1>Create New Event</h1>
    <form class="form-box" method="post" action="/admin/create_event.php" novalidate id="eventForm">
        <?= csrf_field() ?>

        <label for="title">Title</label>
        <input type="text" id="title" name="title" required maxlength="150" value="<?= e($title) ?>">
        <?php if (isset($errors['title'])): ?><div class="error-text"><?= e($errors['title']) ?></div><?php endif; ?>

        <label for="category">Category</label>
        <select id="category" name="category">
            <?php foreach ($categories as $c): ?>
                <option value="<?= e($c) ?>" <?= $c === $category ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
        </select>

        <label for="location">Location</label>
        <input type="text" id="location" name="location" maxlength="150" value="<?= e($location) ?>">

        <label for="event_date">Date &amp; Time</label>
        <input type="datetime-local" id="event_date" name="event_date" required value="<?= e($eventDate) ?>">
        <?php if (isset($errors['event_date'])): ?><div class="error-text"><?= e($errors['event_date']) ?></div><?php endif; ?>

        <label for="total_slots">Total Slots</label>
        <input type="number" id="total_slots" name="total_slots" required min="1" step="1" value="<?= e($totalSlots) ?>">
        <?php if (isset($errors['total_slots'])): ?><div class="error-text"><?= e($errors['total_slots']) ?></div><?php endif; ?>

        <label for="description">
            Description
            <span class="badge-ai">AI</span>
        </label>
        <textarea id="description" name="description" rows="5"><?= e($description) ?></textarea>
        <button type="button" class="btn-ai btn-sm" id="aiGenerateBtn">✨ Generate with AI</button>
        <div class="hint" id="aiStatus"></div>
    </form>
    <p><a href="/admin/index.php">&larr; Back to dashboard</a></p>
</div>

<script>
document.getElementById('aiGenerateBtn').addEventListener('click', function () {
    var btn = this;
    var status = document.getElementById('aiStatus');
    var payload = {
        title: document.getElementById('title').value,
        category: document.getElementById('category').value,
        location: document.getElementById('location').value,
        event_date: document.getElementById('event_date').value,
        notes: document.getElementById('description').value,
        csrf_token: document.querySelector('input[name=csrf_token]').value
    };
    btn.disabled = true;
    status.textContent = 'Generating…';
    fetch('/api/ai_generate_description.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
        .then(function (res) { return res.json(); })
        .then(function (data) {
            if (data.error) {
                status.textContent = data.error;
                return;
            }
            document.getElementById('description').value = data.description;
            status.textContent = data.source === 'ai' ? 'Generated by AI.' : 'Generated (demo mode - add an AI_API_KEY in .env for live generation).';
        })
        .catch(function () { status.textContent = 'Could not generate a description right now.'; })
        .finally(function () { btn.disabled = false; });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
