<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/calendars.php';

/**
 * Event write API (Phase 4).
 *   POST   /api/event.php           create an event
 *   PATCH  /api/event.php           update an event (id in body)
 *   DELETE /api/event.php           delete an event (id in body)
 *
 * Permissions: the user must be able to edit the target calendar
 * (own it, hold an 'editor' share, or be an admin for a public calendar).
 * Recurring events are not edited here (kept read-only for now).
 */

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
if ($method === 'GET') {
    json_out(['error' => 'method_not_allowed', 'message' => 'Use /api/events.php to read.'], 405);
}

$u = current_user();
if (!$u) {
    json_out(['error' => 'unauthorized'], 401);
}
require_same_origin();

$body = read_json_body();

/** Load an event row + its calendar, or 404. */
function load_event(int $id): array {
    $stmt = db()->prepare(
        'SELECT e.*, c.slug AS cal_slug, c.color AS cal_color, c.name AS cal_name
         FROM events e JOIN calendars c ON c.id = e.calendar_id WHERE e.id = ?'
    );
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row) {
        json_out(['error' => 'not_found'], 404);
    }
    return $row;
}

/** Whether the events table has the (phase-5) icon column. Cached; defensive if absent. */
function events_has_icon(): bool {
    static $has = null;
    if ($has === null) {
        try { $has = (bool) db()->query("SHOW COLUMNS FROM events LIKE 'icon'")->fetch(); }
        catch (PDOException $e) { $has = false; }
    }
    return $has;
}

/** Normalise a per-event icon (short emoji string). Empty => null. */
function clean_icon($v): ?string {
    $v = trim((string) $v);
    return $v === '' ? null : mb_substr($v, 0, 16);
}

/** Shape an event row the same way api/events.php does (non-recurring path). */
function shape_event(array $e): array {
    $allDay   = (int) $e['all_day'] === 1;
    $editable = can_edit_calendar((int) $e['calendar_id']);
    return [
        'id'     => (int) $e['id'],
        'title'  => $e['title'],
        'color'  => $e['cal_color'],
        'allDay' => $allDay,
        'start'  => $allDay ? substr($e['starts_at'], 0, 10) : str_replace(' ', 'T', $e['starts_at']) . 'Z',
        'end'    => $allDay ? substr($e['ends_at'], 0, 10)   : str_replace(' ', 'T', $e['ends_at'])   . 'Z',
        'editable' => $editable,
        'extendedProps' => [
            'calendar'     => $e['cal_slug'],
            'calendarId'   => (int) $e['calendar_id'],
            'calendarName' => $e['cal_name'],
            'location'     => $e['location'],
            'description'  => $e['description'],
            'icon'         => $e['icon'] ?? null,
            'recurring'    => false,
            'canEdit'      => $editable,
        ],
    ];
}

/** Validate a recurrence rule body (without the "RRULE:" prefix). Empty => null. */
function clean_rrule($v): ?string {
    $v = strtoupper(trim((string) $v));
    if ($v === '') {
        return null;
    }
    $v = preg_replace('/^RRULE:/', '', $v);
    if (!preg_match('#^[A-Z0-9;=,:/+\-]+$#', $v) || strpos($v, 'FREQ=') === false) {
        json_out(['error' => 'bad_request', 'message' => 'Invalid recurrence rule.'], 400);
    }
    return $v;
}

/** Validate + normalise start/end from the request body. */
function resolve_times(array $body): array {
    $allDay = !empty($body['all_day']);
    $start  = (string) ($body['start'] ?? '');
    $end    = (string) ($body['end'] ?? '');
    if ($start === '') {
        json_out(['error' => 'bad_request', 'message' => 'start is required'], 400);
    }
    try {
        $startUtc = parse_to_utc($start, $allDay);
        if ($end !== '') {
            $endUtc = parse_to_utc($end, $allDay);
        } else {
            $s = new DateTime($startUtc, new DateTimeZone('UTC'));
            $s->modify($allDay ? '+1 day' : '+1 hour');
            $endUtc = $s->format('Y-m-d H:i:s');
        }
    } catch (Exception $e) {
        json_out(['error' => 'bad_request', 'message' => $e->getMessage()], 400);
    }
    if (strtotime($endUtc) <= strtotime($startUtc)) {
        // keep a sane minimum span
        $s = new DateTime($startUtc, new DateTimeZone('UTC'));
        $s->modify($allDay ? '+1 day' : '+1 hour');
        $endUtc = $s->format('Y-m-d H:i:s');
    }
    return [$startUtc, $endUtc, $allDay ? 1 : 0];
}

$pdo = db();

if ($method === 'POST') {
    $calId = (int) ($body['calendar_id'] ?? 0);
    $title = trim((string) ($body['title'] ?? ''));
    if ($calId <= 0 || $title === '') {
        json_out(['error' => 'bad_request', 'message' => 'calendar_id and title are required'], 400);
    }
    if (!can_edit_calendar($calId)) {
        json_out(['error' => 'forbidden', 'message' => 'You cannot add events to this calendar.'], 403);
    }
    if (is_feed_calendar($calId)) {
        json_out(['error' => 'forbidden', 'message' => 'This calendar mirrors a subscription and is read-only.'], 403);
    }
    [$startUtc, $endUtc, $allDay] = resolve_times($body);
    $rrule = array_key_exists('rrule', $body) ? clean_rrule($body['rrule']) : null;

    $cols = ['calendar_id', 'uid', 'title', 'description', 'location', 'starts_at', 'ends_at', 'all_day', 'rrule', 'timezone'];
    $args = [
        ':cal' => $calId, ':uid' => make_event_uid(), ':title' => mb_substr($title, 0, 500),
        ':description' => ($body['description'] ?? null) ?: null,
        ':location'    => ($body['location'] ?? null) ?: null,
        ':starts' => $startUtc, ':ends' => $endUtc, ':allday' => $allDay, ':rrule' => $rrule,
    ];
    $vals = [':cal', ':uid', ':title', ':description', ':location', ':starts', ':ends', ':allday', ':rrule', '"UTC"'];
    if (events_has_icon()) {
        $cols[] = 'icon'; $vals[] = ':icon'; $args[':icon'] = clean_icon($body['icon'] ?? '');
    }
    $sql = 'INSERT INTO events (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
    $pdo->prepare($sql)->execute($args);
    json_out(['event' => shape_event(load_event((int) $pdo->lastInsertId()))], 201);
}

if ($method === 'PATCH' || $method === 'PUT') {
    $id = (int) ($body['id'] ?? 0);
    if ($id <= 0) {
        json_out(['error' => 'bad_request', 'message' => 'id is required'], 400);
    }
    $existing = load_event($id);
    if (!can_edit_calendar((int) $existing['calendar_id'])) {
        json_out(['error' => 'forbidden'], 403);
    }
    if (is_feed_calendar((int) $existing['calendar_id'])) {
        json_out(['error' => 'forbidden', 'message' => 'This calendar mirrors a subscription and is read-only.'], 403);
    }

    $fields = [];
    $args   = [];
    if (array_key_exists('title', $body)) {
        $t = trim((string) $body['title']);
        if ($t === '') {
            json_out(['error' => 'bad_request', 'message' => 'title cannot be empty'], 400);
        }
        $fields[] = 'title = ?'; $args[] = mb_substr($t, 0, 500);
    }
    if (array_key_exists('description', $body)) { $fields[] = 'description = ?'; $args[] = ($body['description'] ?: null); }
    if (array_key_exists('location', $body))    { $fields[] = 'location = ?';    $args[] = ($body['location'] ?: null); }
    if (array_key_exists('rrule', $body))       { $fields[] = 'rrule = ?';       $args[] = clean_rrule($body['rrule']); }
    if (array_key_exists('icon', $body) && events_has_icon()) { $fields[] = 'icon = ?'; $args[] = clean_icon($body['icon']); }

    // Move to another calendar (must be able to edit the destination too).
    if (array_key_exists('calendar_id', $body)) {
        $dest = (int) $body['calendar_id'];
        if ($dest !== (int) $existing['calendar_id']) {
            if (!can_edit_calendar($dest)) {
                json_out(['error' => 'forbidden', 'message' => 'You cannot move events to that calendar.'], 403);
            }
            $fields[] = 'calendar_id = ?'; $args[] = $dest;
        }
    }

    if (array_key_exists('start', $body) || array_key_exists('all_day', $body)) {
        // When timing changes, require a coherent start/end pair from the client.
        $merged = $body;
        if (!array_key_exists('start', $body)) { $merged['start'] = str_replace(' ', 'T', (string) $existing['starts_at']) . 'Z'; }
        [$startUtc, $endUtc, $allDay] = resolve_times($merged);
        $fields[] = 'starts_at = ?'; $args[] = $startUtc;
        $fields[] = 'ends_at = ?';   $args[] = $endUtc;
        $fields[] = 'all_day = ?';   $args[] = $allDay;
    }

    if (!$fields) {
        json_out(['error' => 'bad_request', 'message' => 'nothing to update'], 400);
    }
    $args[] = $id;
    $pdo->prepare('UPDATE events SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($args);
    json_out(['event' => shape_event(load_event($id))]);
}

if ($method === 'DELETE') {
    $id = (int) ($body['id'] ?? 0);
    if ($id <= 0) {
        json_out(['error' => 'bad_request', 'message' => 'id is required'], 400);
    }
    $existing = load_event($id);
    if (!can_edit_calendar((int) $existing['calendar_id'])) {
        json_out(['error' => 'forbidden'], 403);
    }
    if (is_feed_calendar((int) $existing['calendar_id'])) {
        json_out(['error' => 'forbidden', 'message' => 'This calendar mirrors a subscription and is read-only.'], 403);
    }
    $pdo->prepare('DELETE FROM events WHERE id = ?')->execute([$id]);
    json_out(['ok' => true]);
}

json_out(['error' => 'method_not_allowed'], 405);
