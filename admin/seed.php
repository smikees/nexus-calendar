<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';

// Admin-only bootstrap of sample PUBLIC calendars + events. Idempotent.
if (!is_admin()) {
    http_response_code(403);
    exit('Admins only');
}

$pdo = db();

function upsert_calendar(PDO $pdo, array $c): int {
    $stmt = $pdo->prepare(
        'INSERT INTO calendars (owner_user_id, slug, name, description, color, icon, visibility, default_priority, timezone)
         VALUES (NULL, :slug, :name, :description, :color, :icon, "public", :priority, "UTC")
         ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description),
            color=VALUES(color), icon=VALUES(icon), default_priority=VALUES(default_priority)'
    );
    $stmt->execute([
        ':slug' => $c['slug'], ':name' => $c['name'], ':description' => $c['description'],
        ':color' => $c['color'], ':icon' => $c['icon'], ':priority' => $c['priority'],
    ]);
    $id = $pdo->prepare('SELECT id FROM calendars WHERE slug = ?');
    $id->execute([$c['slug']]);
    return (int) $id->fetchColumn();
}

function upsert_event(PDO $pdo, int $calId, array $e): void {
    $stmt = $pdo->prepare(
        'INSERT INTO events (calendar_id, uid, title, description, location, starts_at, ends_at, all_day, rrule, timezone)
         VALUES (:cal, :uid, :title, :description, :location, :starts, :ends, :allday, :rrule, "UTC")
         ON DUPLICATE KEY UPDATE title=VALUES(title), description=VALUES(description),
            location=VALUES(location), starts_at=VALUES(starts_at), ends_at=VALUES(ends_at),
            all_day=VALUES(all_day), rrule=VALUES(rrule), calendar_id=VALUES(calendar_id)'
    );
    $stmt->execute([
        ':cal' => $calId, ':uid' => $e['uid'], ':title' => $e['title'],
        ':description' => $e['description'] ?? null, ':location' => $e['location'] ?? null,
        ':starts' => $e['starts'], ':ends' => $e['ends'], ':allday' => $e['allday'] ?? 0, ':rrule' => $e['rrule'] ?? null,
    ]);
}

$finance = upsert_calendar($pdo, [
    'slug' => 'finance', 'name' => 'Finance', 'color' => '#38bdf8', 'icon' => '📈',
    'priority' => 10, 'description' => 'Macro events and public-company earnings.',
]);
foreach ([
    ['uid'=>'fin-cpi-202606','title'=>'US CPI Release','starts'=>'2026-06-10 12:30:00','ends'=>'2026-06-10 13:00:00'],
    ['uid'=>'fin-fomc-202606','title'=>'FOMC Rate Decision','starts'=>'2026-06-17 18:00:00','ends'=>'2026-06-17 18:30:00'],
    ['uid'=>'fin-nvda-202606','title'=>'Nvidia Earnings (sample)','starts'=>'2026-06-24 20:00:00','ends'=>'2026-06-24 21:00:00'],
    ['uid'=>'fin-jobs-202607','title'=>'US Jobs Report','starts'=>'2026-07-02 12:30:00','ends'=>'2026-07-02 13:00:00'],
    ['uid'=>'fin-recap-weekly','title'=>'Weekly Market Recap','starts'=>'2026-06-05 21:00:00','ends'=>'2026-06-05 21:30:00','rrule'=>'FREQ=WEEKLY;BYDAY=FR'],
] as $e) { upsert_event($pdo, $finance, $e); }

$wc = upsert_calendar($pdo, [
    'slug' => 'world-cup', 'name' => '2026 World Cup', 'color' => '#22c55e', 'icon' => '⚽',
    'priority' => 20, 'description' => 'FIFA World Cup 2026 matches and ceremonies.',
]);
foreach ([
    ['uid'=>'wc-open-2026','title'=>'Opening Match','location'=>'Estadio Azteca, Mexico City','starts'=>'2026-06-11 19:00:00','ends'=>'2026-06-11 21:00:00'],
    ['uid'=>'wc-group-usa-2026','title'=>'Group Stage: USA','starts'=>'2026-06-15 23:00:00','ends'=>'2026-06-16 01:00:00'],
    ['uid'=>'wc-r16-2026','title'=>'Round of 16 begins','starts'=>'2026-06-28 00:00:00','ends'=>'2026-06-29 00:00:00','allday'=>1],
    ['uid'=>'wc-final-2026','title'=>'World Cup Final','location'=>'MetLife Stadium','starts'=>'2026-07-19 19:00:00','ends'=>'2026-07-19 21:30:00'],
] as $e) { upsert_event($pdo, $wc, $e); }

header('Location: /?seeded=1');
exit;
