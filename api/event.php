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

/** Whether the events table has the exdates column (base schema; defensive). Cached. */
function events_has_exdates(): bool {
    static $has = null;
    if ($has === null) {
        try { $has = (bool) db()->query("SHOW COLUMNS FROM events LIKE 'exdates'")->fetch(); }
        catch (PDOException $e) { $has = false; }
    }
    return $has;
}

/** Compact RECURRENCE-ID token for an override uid, e.g. 20260715T190000Z or 20260715. */
function recurrence_token(string $utcSql, bool $allDay): string {
    $d = new DateTime($utcSql, new DateTimeZone('UTC'));
    return $allDay ? $d->format('Ymd') : $d->format('Ymd\THis\Z');
}

/** Add an excluded occurrence (UTC 'Y-m-d H:i:s') to a series' exdates, de-duplicated. */
function add_series_exdate(PDO $pdo, array $series, string $recUtcSql): void {
    $cur  = (string) ($series['exdates'] ?? '');
    $list = array_values(array_filter(array_map('trim', preg_split('/[,\r\n]+/', $cur))));
    $want = (new DateTime($recUtcSql, new DateTimeZone('UTC')))->format('Y-m-d H:i:s');
    foreach ($list as $x) {
        try {
            if ((new DateTime($x, new DateTimeZone('UTC')))->format('Y-m-d H:i:s') === $want) {
                return; // already excluded
            }
        } catch (Exception $e) { /* skip junk */ }
    }
    $list[] = $want;
    $pdo->prepare('UPDATE events SET exdates = ? WHERE id = ?')->execute([implode(',', $list), (int) $series['id']]);
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

    // ---- Edit a SINGLE occurrence of a recurring series (Outlook-style) ----
    // Suppress the original occurrence via EXDATE and materialise a detached
    // override event (uid = "<seriesUid>::<recurrenceToken>"). Idempotent per occurrence.
    $isRecurringSeries = isset($existing['rrule']) && trim((string) $existing['rrule']) !== '';
    if (($body['scope'] ?? '') === 'occurrence' && $isRecurringSeries) {
        if (!events_has_exdates()) {
            json_out(['error' => 'migration_required', 'message' => 'The exdates column is missing; run db_setup.sql / migration.'], 503);
        }
        $allDaySeries = (int) $existing['all_day'] === 1;
        $recRaw = (string) ($body['recurrence_id'] ?? '');
        if ($recRaw === '') {
            json_out(['error' => 'bad_request', 'message' => 'recurrence_id is required for an occurrence edit'], 400);
        }
        try { $recUtc = parse_to_utc($recRaw, $allDaySeries); }
        catch (Exception $e) { json_out(['error' => 'bad_request', 'message' => 'invalid recurrence_id'], 400); }

        // Destination calendar (allow moving a single occurrence too).
        $calId = (int) $existing['calendar_id'];
        if (array_key_exists('calendar_id', $body)) {
            $dest = (int) $body['calendar_id'];
            if ($dest !== $calId) {
                if (!can_edit_calendar($dest)) {
                    json_out(['error' => 'forbidden', 'message' => 'You cannot move events to that calendar.'], 403);
                }
                $calId = $dest;
            }
        }

        // Resolve the override's start/end: from the request when timing changed,
        // otherwise the original slot (recurrence start + the series' duration).
        if (array_key_exists('start', $body)) {
            [$startUtc, $endUtc, $allDay] = resolve_times($body);
        } else {
            $sSeries = new DateTime($existing['starts_at'], new DateTimeZone('UTC'));
            $eSeries = new DateTime($existing['ends_at'], new DateTimeZone('UTC'));
            $dur = max(0, $eSeries->getTimestamp() - $sSeries->getTimestamp());
            $startUtc = $recUtc;
            $eo = new DateTime($recUtc, new DateTimeZone('UTC'));
            $eo->modify('+' . $dur . ' seconds');
            $endUtc = $eo->format('Y-m-d H:i:s');
            $allDay = $allDaySeries ? 1 : 0;
        }

        $title = array_key_exists('title', $body) ? trim((string) $body['title']) : (string) $existing['title'];
        if ($title === '') { $title = (string) $existing['title']; }
        $desc = array_key_exists('description', $body) ? (($body['description'] ?: null)) : $existing['description'];
        $loc  = array_key_exists('location', $body)    ? (($body['location'] ?: null))    : $existing['location'];

        add_series_exdate($pdo, $existing, $recUtc);

        $ovUid = ((string) $existing['uid']) . '::' . recurrence_token($recUtc, $allDaySeries);
        $cols = ['calendar_id', 'uid', 'title', 'description', 'location', 'starts_at', 'ends_at', 'all_day', 'rrule', 'exdates', 'timezone'];
        $vals = [':cal', ':uid', ':title', ':desc', ':loc', ':starts', ':ends', ':allday', 'NULL', 'NULL', '"UTC"'];
        $args = [
            ':cal' => $calId, ':uid' => $ovUid, ':title' => mb_substr($title, 0, 500),
            ':desc' => $desc, ':loc' => $loc, ':starts' => $startUtc, ':ends' => $endUtc, ':allday' => (int) $allDay,
        ];
        $updates = 'title=VALUES(title), description=VALUES(description), location=VALUES(location), '
                 . 'starts_at=VALUES(starts_at), ends_at=VALUES(ends_at), all_day=VALUES(all_day), calendar_id=VALUES(calendar_id)';
        if (events_has_icon()) {
            $cols[] = 'icon'; $vals[] = ':icon';
            $args[':icon'] = array_key_exists('icon', $body) ? clean_icon($body['icon']) : ($existing['icon'] ?? null);
            $updates .= ', icon=VALUES(icon)';
        }
        $sql = 'INSERT INTO events (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ') '
             . 'ON DUPLICATE KEY UPDATE ' . $updates;
        $pdo->prepare($sql)->execute($args);

        $ov = db()->prepare(
            'SELECT e.*, c.slug AS cal_slug, c.color AS cal_color, c.name AS cal_name
             FROM events e JOIN calendars c ON c.id = e.calendar_id WHERE e.uid = ?'
        );
        $ov->execute([$ovUid]);
        $ovRow = $ov->fetch();
        json_out(['event' => $ovRow ? shape_event($ovRow) : null, 'occurrence' => true]);
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

    $isRecurringSeries = isset($existing['rrule']) && trim((string) $existing['rrule']) !== '';

    // Delete a single occurrence: EXDATE it and drop any detached override.
    if (($body['scope'] ?? '') === 'occurrence' && $isRecurringSeries) {
        if (!events_has_exdates()) {
            json_out(['error' => 'migration_required', 'message' => 'The exdates column is missing; run db_setup.sql / migration.'], 503);
        }
        $allDaySeries = (int) $existing['all_day'] === 1;
        $recRaw = (string) ($body['recurrence_id'] ?? '');
        if ($recRaw === '') {
            json_out(['error' => 'bad_request', 'message' => 'recurrence_id is required'], 400);
        }
        try { $recUtc = parse_to_utc($recRaw, $allDaySeries); }
        catch (Exception $e) { json_out(['error' => 'bad_request', 'message' => 'invalid recurrence_id'], 400); }
        add_series_exdate($pdo, $existing, $recUtc);
        $ovUid = ((string) $existing['uid']) . '::' . recurrence_token($recUtc, $allDaySeries);
        $pdo->prepare('DELETE FROM events WHERE uid = ?')->execute([$ovUid]);
        json_out(['ok' => true, 'occurrence' => true]);
    }

    // Delete the whole event/series (and any detached occurrence overrides).
    $pdo->prepare('DELETE FROM events WHERE id = ?')->execute([$id]);
    if ($isRecurringSeries) {
        $pdo->prepare('DELETE FROM events WHERE uid LIKE ?')->execute([str_replace(['%', '_'], ['\\%', '\\_'], (string) $existing['uid']) . '::%']);
    }
    json_out(['ok' => true]);
}

json_out(['error' => 'method_not_allowed'], 405);
