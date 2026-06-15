<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/calendars.php';

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
    // Drag/resize is allowed for editable, non-recurring events only.
    $canEdit    = isset($editable[(int) $e['calendar_id']]);
    $base = [
        'id'    => (int) $e['id'],
        'title' => $e['title'],
        'color' => $e['cal_color'],
        'allDay'=> $allDay,
        'editable' => $canEdit && !$recurring,
        'extendedProps' => [
            'calendar'     => $e['cal_slug'],
            'calendarId'   => (int) $e['calendar_id'],
            'calendarName' => $e['cal_name'],
            'location'     => $e['location'],
            'description'  => $e['description'],
            'recurring'    => $recurring,
            'canEdit'      => $canEdit,
        ],
    ];
    if ($recurring) {
        $s = new DateTime($e['starts_at'], new DateTimeZone('UTC'));
        $en = new DateTime($e['ends_at'], new DateTimeZone('UTC'));
        $dur = max(0, $en->getTimestamp() - $s->getTimestamp());
        $base['rrule']    = 'DTSTART:' . $s->format('Ymd\THis\Z') . "\nRRULE:" . $e['rrule'];
        $base['duration'] = sprintf('%02d:%02d', intdiv($dur, 3600), intdiv($dur % 3600, 60));
    } else {
        $base['start'] = $allDay ? substr($e['starts_at'], 0, 10) : str_replace(' ', 'T', $e['starts_at']) . 'Z';
        $base['end']   = $allDay ? substr($e['ends_at'], 0, 10)   : str_replace(' ', 'T', $e['ends_at'])   . 'Z';
    }
    $events[] = $base;
}
json_out(['events' => $events]);
