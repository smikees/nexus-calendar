<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/calendars.php';
require_once __DIR__ . '/../lib/ical.php';

/**
 * iCalendar export (Phase 6).
 *   GET /api/export.php?token=<feed_token>     public subscription feed (no auth)
 *   GET /api/export.php?calendar_id=<id>       authenticated download (attachment)
 * Read-only GET; safe to fetch with the session cookie (no CSRF concern).
 */

$pdo   = db();
$token = trim((string) ($_GET['token'] ?? ''));
$calId = (int) ($_GET['calendar_id'] ?? 0);

function export_load_events(PDO $pdo, int $calId): array {
    $stmt = $pdo->prepare('SELECT * FROM events WHERE calendar_id = ? ORDER BY starts_at ASC');
    $stmt->execute([$calId]);
    return $stmt->fetchAll();
}

function export_serve(array $cal, array $events, bool $attachment): void {
    $ics   = ical_export($cal, $events);
    $fname = preg_replace('/[^A-Za-z0-9_-]+/', '-', strtolower((string) ($cal['name'] ?? 'calendar')));
    $fname = trim((string) $fname, '-');
    if ($fname === '') { $fname = 'calendar'; }
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: ' . ($attachment ? 'attachment' : 'inline') . '; filename="' . $fname . '.ics"');
    header('Cache-Control: no-cache');
    echo $ics;
    exit;
}

/* ---- public feed by secret token (no auth) ---- */
if ($token !== '') {
    try {
        $stmt = $pdo->prepare('SELECT * FROM calendars WHERE feed_token = ? LIMIT 1');
        $stmt->execute([$token]);
        $cal = $stmt->fetch();
    } catch (PDOException $e) { $cal = null; }
    if (!$cal) {
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Calendar feed not found.';
        exit;
    }
    export_serve($cal, export_load_events($pdo, (int) $cal['id']), false);
}

/* ---- authenticated download by calendar_id ---- */
if ($calId <= 0) {
    json_out(['error' => 'bad_request', 'message' => 'calendar_id or token is required'], 400);
}
$stmt = $pdo->prepare('SELECT * FROM calendars WHERE id = ?');
$stmt->execute([$calId]);
$cal = $stmt->fetch();
if (!$cal) {
    json_out(['error' => 'not_found'], 404);
}
$canView = ($cal['visibility'] === 'public');
if (!$canView) {
    $u = current_user();
    if ($u) {
        if ((int) $cal['owner_user_id'] === (int) $u['uid'] || is_admin($u)) {
            $canView = true;
        } else {
            $shared  = shared_calendar_roles();
            $canView = isset($shared[(int) $calId]);
        }
    }
}
if (!$canView) {
    json_out(['error' => 'forbidden'], 403);
}
export_serve($cal, export_load_events($pdo, $calId), true);
