<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/calendars.php';

// Event data is user/state dependent and changes whenever a calendar is edited
// or re-seeded; never let the browser serve a stale cached copy.
header('Cache-Control: no-store, max-age=0');

/**
 * Build an "EXDATE" line (leading newline) for a recurring event from its stored
 * exdates (comma/newline separated UTC datetimes). Empty => ''.
 * All-day series use VALUE=DATE tokens; timed series use UTC Z tokens.
 */
function exdate_line($exdates, bool $allDay): string {
    if ($exdates === null || trim((string) $exdates) === '') {
        return '';
    }
    $toks = [];
    foreach (preg_split('/[,\r\n]+/', trim((string) $exdates)) as $raw) {
        $raw = trim($raw);
        if ($raw === '') { continue; }
        try { $d = new DateTime($raw, new DateTimeZone('UTC')); }
        catch (Exception $e) { continue; }
        $toks[] = $allDay ? $d->format('Ymd') : $d->format('Ymd\THis\Z');
    }
    if (!$toks) { return ''; }
    return $allDay
        ? "\nEXDATE;VALUE=DATE:" . implode(',', $toks)
        : "\nEXDATE:" . implode(',', $toks);
}

$from = (string) ($_GET['from'] ?? '');
$to   = (string) ($_GET['to'] ?? '');
$cals = array_filter(explode(',', (string) ($_GET['cals'] ?? '')));

if ($from === '' || $to === '' || !$cals) {
    json_out(['events' => []]);
}

try {
    $fromUtc = (new DateTime($from))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $toUtc   = (new DateTime($to))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
} catch (Exception $e) {
    json_out(['events' => []], 400);
}

$allowed = [];
foreach (visible_calendars() as $c) {
    $allowed[$c['slug']] = $c;
}
$slugs = array_values(array_intersect($cals, array_keys($allowed)));
if (!$slugs) {
    json_out(['events' => []]);
}

// Recurring rows: include any whose first instance starts before the window end
// (the client's rrule plugin expands occurrences within the view).
// One-time rows: standard overlap with the window.
$ph  = implode(',', array_fill(0, count($slugs), '?'));
$sql = "SELECT e.*, c.slug AS cal_slug, c.color AS cal_color, c.name AS cal_name
        FROM events e
        JOIN calendars c ON c.id = e.calendar_id
        WHERE c.slug IN ($ph)
          AND ( (e.rrule IS NOT NULL AND e.rrule <> '' AND e.starts_at < ?)
                OR ((e.rrule IS NULL OR e.rrule = '') AND e.starts_at < ? AND e.ends_at > ?) )
        ORDER BY e.starts_at ASC";
$stmt = db()->prepare($sql);
$stmt->execute(array_merge($slugs, [$toUtc, $toUtc, $fromUtc]));

$editable = editable_calendar_ids();

$events = [];
foreach ($stmt->fetchAll() as $e) {
    $allDay     = (int) $e['all_day'] === 1;
    $recurring  = isset($e['rrule']) && $e['rrule'] !== null && $e['rrule'] !== '';
    // Editable calendars allow drag/resize; recurring events drag a single
    // occurrence (the client sends scope=occurrence with a recurrence id).
    $canEdit    = isset($editable[(int) $e['calendar_id']]);
    $startUtc   = $allDay ? substr($e['starts_at'], 0, 10) : str_replace(' ', 'T', $e['starts_at']) . 'Z';
    $endUtc     = $allDay ? substr($e['ends_at'], 0, 10)   : str_replace(' ', 'T', $e['ends_at'])   . 'Z';
    $base = [
        'id'    => (int) $e['id'],
        'title' => $e['title'],
        'color' => $e['cal_color'],
        'allDay'=> $allDay,
        'editable' => $canEdit,
        'extendedProps' => [
            'calendar'     => $e['cal_slug'],
            'calendarId'   => (int) $e['calendar_id'],
            'calendarName' => $e['cal_name'],
            'location'     => $e['location'],
            'description'  => $e['description'],
            'icon'         => $e['icon'] ?? null,
            'recurring'    => $recurring,
            'canEdit'      => $canEdit,
            'startUtc'     => $startUtc,   // series base start (for editing recurring)
            'endUtc'       => $endUtc,
            'rruleBody'    => $recurring ? $e['rrule'] : '',
        ],
    ];
    if ($recurring) {
        $s  = new DateTime($e['starts_at'], new DateTimeZone('UTC'));
        $en = new DateTime($e['ends_at'], new DateTimeZone('UTC'));
        // Suppress occurrences that have been individually edited or deleted.
        $ex = exdate_line($e['exdates'] ?? null, $allDay);
        if ($allDay) {
            $base['rrule'] = 'DTSTART;VALUE=DATE:' . $s->format('Ymd') . "\nRRULE:" . $e['rrule'] . $ex;
            $days = (int) max(1, round(($en->getTimestamp() - $s->getTimestamp()) / 86400));
            if ($days > 1) { $base['duration'] = ['days' => $days]; }
        } else {
            $dur = max(0, $en->getTimestamp() - $s->getTimestamp());
            $base['rrule']    = 'DTSTART:' . $s->format('Ymd\THis\Z') . "\nRRULE:" . $e['rrule'] . $ex;
            $base['duration'] = sprintf('%02d:%02d', intdiv($dur, 3600), intdiv($dur % 3600, 60));
        }
    } else {
        $base['start'] = $startUtc;
        $base['end']   = $endUtc;
    }
    $events[] = $base;
}
json_out(['events' => $events]);
