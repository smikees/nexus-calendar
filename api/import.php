<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/calendars.php';
require_once __DIR__ . '/../lib/ical.php';

/**
 * One-time .ics file import (Phase 6).
 *   POST /api/import.php  (multipart/form-data)
 *     file         the .ics upload (required)
 *     calendar_id  import into an existing editable calendar, OR
 *     name         create a new private calendar with this name (+ optional color, icon)
 * Additive: events are inserted (not replaced).
 */

$u = current_user();
if (!$u) { json_out(['error' => 'unauthorized'], 401); }
require_same_origin();
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_out(['error' => 'method_not_allowed'], 405);
}

if (empty($_FILES['file']) || (($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK)) {
    json_out(['error' => 'bad_request', 'message' => 'No .ics file uploaded.'], 400);
}
$f = $_FILES['file'];
if (($f['size'] ?? 0) > 5 * 1024 * 1024) {
    json_out(['error' => 'bad_request', 'message' => 'File too large (max 5 MB).'], 400);
}
$text = file_get_contents($f['tmp_name']);
if ($text === false || stripos($text, 'BEGIN:VCALENDAR') === false) {
    json_out(['error' => 'bad_request', 'message' => "That doesn't look like an iCalendar (.ics) file."], 400);
}
$events = ical_parse($text);
if (!$events) {
    json_out(['error' => 'bad_request', 'message' => 'No events found in the file.'], 400);
}

$pdo   = db();
$calId = (int) ($_POST['calendar_id'] ?? 0);
$slug  = null;

if ($calId > 0) {
    if (!can_edit_calendar($calId)) { json_out(['error' => 'forbidden'], 403); }
    if (is_feed_calendar($calId)) {
        json_out(['error' => 'forbidden', 'message' => 'That calendar mirrors a feed; import into a different calendar.'], 403);
    }
} else {
    $name = trim((string) ($_POST['name'] ?? ''));
    if ($name === '') {
        json_out(['error' => 'bad_request', 'message' => 'Pick a calendar or enter a name for a new one.'], 400);
    }
    $color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($_POST['color'] ?? '')) ? (string) $_POST['color'] : '#5b9dd9';
    $icon  = trim((string) ($_POST['icon'] ?? ''));
    $icon  = $icon === '' ? null : mb_substr($icon, 0, 16);
    $c     = create_private_calendar((int) $u['uid'], $name, $color, $icon, null);
    $calId = $c['id'];
    $slug  = $c['slug'];
}

$n = ical_store_events($pdo, $calId, $events, false);
json_out(['imported' => $n, 'calendar_id' => $calId, 'slug' => $slug], 201);
