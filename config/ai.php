<?php
/**
 * Thin AI abstraction layer.
 *
 * If AI_API_KEY is set in .env, calls the configured provider
 * (Anthropic or OpenAI-compatible chat completion) over HTTPS.
 * If no key is configured, every function falls back to a
 * deterministic, templated "stub" response - so the feature is
 * fully usable and demo-able with zero cost/setup, and starts
 * making real model calls the moment a key is added, with no
 * other code changes required.
 */
require_once __DIR__ . '/env.php';
load_env();

function ai_is_live(): bool
{
    return env('AI_API_KEY', '') !== '';
}

/**
 * Low-level call to the configured provider. Returns null on any
 * failure (network error, bad key, timeout) so callers can fall
 * back gracefully instead of breaking the page.
 */
function ai_complete(string $systemPrompt, string $userPrompt): ?string
{
    if (!ai_is_live()) {
        return null;
    }

    $provider = strtolower(env('AI_PROVIDER', 'anthropic'));
    $apiKey   = env('AI_API_KEY');
    $model    = env('AI_MODEL', 'claude-sonnet-5');

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);
    curl_setopt($ch, CURLOPT_POST, true);

    if ($provider === 'openai') {
        curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'max_tokens' => 400,
        ]));
    } else {
        curl_setopt($ch, CURLOPT_URL, 'https://api.anthropic.com/v1/messages');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'x-api-key: ' . $apiKey,
            'anthropic-version: 2023-06-01',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => $model,
            'system' => $systemPrompt,
            'max_tokens' => 400,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]));
    }

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($response === false || $status < 200 || $status >= 300) {
        error_log("AI provider call failed (status $status)");
        return null;
    }

    $data = json_decode($response, true);
    if ($provider === 'openai') {
        return $data['choices'][0]['message']['content'] ?? null;
    }
    return $data['content'][0]['text'] ?? null;
}

/**
 * Generate a polished event description from a few organizer-supplied
 * facts. Falls back to a rule-based template when no AI key is set.
 */
function ai_generate_description(string $title, string $category, string $location, string $dateHuman, string $notes = ''): string
{
    $system = 'You write concise, energetic event marketing copy (2-3 sentences, no hashtags, no emoji spam).';
    $user = "Event title: $title\nCategory: $category\nLocation: $location\nDate: $dateHuman\nOrganizer notes: $notes\n\nWrite the description.";

    $live = ai_complete($system, $user);
    if ($live !== null) {
        return trim($live);
    }

    // --- Stub fallback: deterministic, still reads naturally ---
    $openers = [
        'Technology'  => "Join us for $title, where ideas meet action.",
        'Careers'     => "Take the next step in your career at $title.",
        'Community'   => "Come together with your community at $title.",
        'Health'      => "Prioritize your wellbeing and show up for $title.",
        'Business'    => "Sharpen your edge and grow your network at $title.",
        'Culture'     => "Celebrate and connect at $title.",
        'Sports'      => "Bring your energy - $title is on.",
        'General'     => "Don't miss $title.",
    ];
    $opener = $openers[$category] ?? $openers['General'];
    $notesPart = $notes !== '' ? ' ' . rtrim($notes, '.') . '.' : '';

    return sprintf(
        '%s Taking place at %s on %s, this %s event is designed to be worth your time.%s Spots are limited, so register early to guarantee your place.',
        $opener,
        $location !== '' ? $location : 'a venue to be confirmed',
        $dateHuman,
        strtolower($category),
        $notesPart
    );
}

/**
 * Answer an attendee's free-text question about a specific event.
 * Falls back to simple keyword matching against a small FAQ set
 * built from the event's own data when no AI key is set.
 */
function ai_answer_question(array $event, string $question): string
{
    $context = sprintf(
        "Title: %s\nDescription: %s\nLocation: %s\nDate: %s\nSlots remaining: %d of %d\nCategory: %s",
        $event['title'],
        $event['description'] ?? '',
        $event['location'] ?? 'TBA',
        $event['event_date'],
        max(0, (int)($event['slots_remaining'] ?? 0)),
        (int)($event['total_slots'] ?? 0),
        $event['category'] ?? 'General'
    );

    $system = 'You are a friendly event assistant. Answer only using the event details provided. '
        . 'If the question cannot be answered from those details, say so briefly and suggest contacting the organizer. '
        . 'Keep answers under 3 sentences.';
    $user = "Event details:\n$context\n\nAttendee question: $question";

    $live = ai_complete($system, $user);
    if ($live !== null) {
        return trim($live);
    }

    // --- Stub fallback: keyword-based FAQ matching over event data ---
    $q = strtolower($question);
    $slotsRemaining = max(0, (int)($event['slots_remaining'] ?? 0));

    if (str_contains($q, 'where') || str_contains($q, 'location') || str_contains($q, 'venue')) {
        return 'This event takes place at ' . ($event['location'] ?: 'a location to be confirmed') . '.';
    }
    if (str_contains($q, 'when') || str_contains($q, 'date') || str_contains($q, 'time')) {
        return 'It\'s scheduled for ' . date('d M Y \a\t H:i', strtotime($event['event_date'])) . '.';
    }
    if (str_contains($q, 'slot') || str_contains($q, 'space') || str_contains($q, 'full') || str_contains($q, 'available')) {
        return $slotsRemaining > 0
            ? "There are currently $slotsRemaining slot(s) remaining out of {$event['total_slots']}."
            : 'This event is currently full, but you can join the waitlist and we\'ll notify you if a spot opens up.';
    }
    if (str_contains($q, 'cancel') || str_contains($q, 'refund') || str_contains($q, 'withdraw')) {
        return 'To cancel or change a registration, please contact the event organizer directly - this demo build does not yet automate cancellations.';
    }
    if (str_contains($q, 'cost') || str_contains($q, 'price') || str_contains($q, 'fee') || str_contains($q, 'free')) {
        return 'This event is free to register for through the platform.';
    }

    return "I don't have that specific detail yet, but here's what I know: \"" .
        mb_strimwidth($event['description'] ?? $event['title'], 0, 140, '...') . '" Feel free to ask about the date, location, or availability.';
}

/**
 * Suggest events for a user based on categories of events they've
 * already registered for. Rule-based (co-occurring category match);
 * swap for embeddings-based similarity once an AI key is live.
 */
function ai_recommend_events(PDO $pdo, int $userId, int $limit = 3): array
{
    $stmt = $pdo->prepare(
        "SELECT DISTINCT e.category FROM registrations r
         JOIN events e ON e.id = r.event_id
         WHERE r.user_id = ?"
    );
    $stmt->execute([$userId]);
    $categories = array_column($stmt->fetchAll(), 'category');

    if (empty($categories)) {
        $stmt = $pdo->prepare(
            "SELECT e.id, e.title, e.category, e.event_date,
                    e.total_slots - COUNT(r.id) AS slots_remaining
             FROM events e LEFT JOIN registrations r ON r.event_id = e.id
             WHERE e.event_date >= CURRENT_TIMESTAMP
             GROUP BY e.id
             ORDER BY e.event_date ASC
             LIMIT ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    $placeholders = implode(',', array_fill(0, count($categories), '?'));
    $stmt = $pdo->prepare(
        "SELECT e.id, e.title, e.category, e.event_date,
                e.total_slots - COUNT(r.id) AS slots_remaining
         FROM events e LEFT JOIN registrations r ON r.event_id = e.id
         WHERE e.category IN ($placeholders)
           AND e.event_date >= CURRENT_TIMESTAMP
           AND e.id NOT IN (SELECT event_id FROM registrations WHERE user_id = ?)
         GROUP BY e.id
         ORDER BY e.event_date ASC
         LIMIT " . (int)$limit
    );
    $stmt->execute([...$categories, $userId]);
    return $stmt->fetchAll();
}
