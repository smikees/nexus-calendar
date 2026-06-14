<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/calendars.php';

$from = (string) ($_GET['from'] ?? '');
$to   = (string) ($_GET['to'] ?? '');
$cals = array_filter(explode(',', (string) ($_GET['cals'] ?? '')));

if ($from === '' || $to === '' || !$cals) {
    json_out(['events' => []]);
}

// Bound the window to UTC datetime strings.
try {
    $fromUtc = (new DateTime($from))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    $toUtc   = (new DateTime($to))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
} catch (Exception $e) {
    json_out(['events' => []], 400);
}

// Restrict to calendars the requester may see.
$allowed = [];
foreach (visible_calendars() as $c) {
    $allowed[$c['slug']] = $c;
}
$slugs = array_values(array_intersect($cals, array_keys($allowed)));
if (!$slugs) {
    json_out(['events' => []]);
}

$ph = implode(',', array_fill(0, count($slugs), '?'));
$sql = "SELECT e.*, c.slug AS cal_slug, c.color AS cal_color, c.name AS cal_name
        FROM events e
        JOIN calendars c ON c.id = e.calendar_id
        WHERE c.slug IN ($ph)
          AND e.starts_at < ? AND e.ends_at > ?
        ORDER BY e.starts_at ASC";
$stmt = db()->prepare($sql);
$stmt->execute(array_merge($slugs, [$toUtc, $fromUtc]));

$events = [];
foreach ($stmt->fetchAll() as $e) {
    $allDay = (int) $e['all_day'] === 1;
    $events[] = [
        'id'      => (int) $e['id'],
        'title'   => $e['title'],
        'start'   => $allDay ? substr($e['starts_at'], 0, 10) : str_replace(' ', 'T', $e['starts_at']) . 'Z',
        'end'     => $allDay ? substr($e['ends_at'], 0, 10)   : str_replace(' ', 'T', $e['ends_at'])   . 'Z',
        'allDay'  => $allDay,
        'color'   => $e['cal_color'],
        'extendedProps' => [
            'calendar'    => $e['cal_slug'],
            'calendarName'=> $e['cal_name'],
            'location'    => $e['location'],
            'description' => $e['description'],
        ],
    ];
}
json_out(['events' => $events]);
