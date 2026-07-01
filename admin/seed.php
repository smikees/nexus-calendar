<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';

// Admin-only bootstrap of sample/curated PUBLIC calendars + events. Idempotent.
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

// ---- Finance (sample) ----
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

// ---- 2026 FIFA World Cup (full schedule; times stored UTC) ----
// Played matches carry the final score in the TITLE (score = title team order).
// Kickoff times verified against the official FIFA schedule (2026-07-01).
$wc = upsert_calendar($pdo, [
    'slug' => 'world-cup', 'name' => '2026 World Cup', 'color' => '#22c55e', 'icon' => '⚽',
    'priority' => 20, 'description' => 'FIFA World Cup 2026 — all 104 matches across USA, Canada, Mexico. Kickoff times shown in your local timezone.',
]);
// drop any stale World Cup sample events
$pdo->prepare("DELETE FROM events WHERE calendar_id = ? AND uid NOT LIKE 'wc-g-%' AND uid NOT LIKE 'wc-ko-%'")->execute([$wc]);

$wc_matches = [
    ['uid'=>'wc-g-001','title'=>'Mexico 2-0 South Africa','description'=>'Group A','location'=>'Mexico City Stadium, Mexico City, Mexico','starts'=>'2026-06-11 19:00:00','ends'=>'2026-06-11 21:00:00'],
    ['uid'=>'wc-g-002','title'=>'South Korea 2-1 Czechia','description'=>'Group A','location'=>'Estadio Guadalajara, Zapopan, Mexico','starts'=>'2026-06-12 02:00:00','ends'=>'2026-06-12 04:00:00'],
    ['uid'=>'wc-g-003','title'=>'Canada 1-1 Bosnia','description'=>'Group B','location'=>'Toronto Stadium, Toronto, Canada','starts'=>'2026-06-12 19:00:00','ends'=>'2026-06-12 21:00:00'],
    ['uid'=>'wc-g-004','title'=>'USA 4-1 Paraguay','description'=>'Group D','location'=>'Los Angeles Stadium, Los Angeles, US','starts'=>'2026-06-13 01:00:00','ends'=>'2026-06-13 03:00:00'],
    ['uid'=>'wc-g-005','title'=>'Qatar 1-1 Switzerland','description'=>'Group B','location'=>'San Francisco Bay Area Stadium, San Francisco, US','starts'=>'2026-06-13 19:00:00','ends'=>'2026-06-13 21:00:00'],
    ['uid'=>'wc-g-006','title'=>'Brazil 1-1 Morocco','description'=>'Group C','location'=>'New York New Jersey Stadium, New Jersey, US','starts'=>'2026-06-13 22:00:00','ends'=>'2026-06-14 00:00:00'],
    ['uid'=>'wc-g-007','title'=>'Haiti 0-1 Scotland','description'=>'Group C','location'=>'Boston Stadium, Boston, US','starts'=>'2026-06-14 01:00:00','ends'=>'2026-06-14 03:00:00'],
    ['uid'=>'wc-g-008','title'=>'Australia 2-0 Turkiye','description'=>'Group D','location'=>'BC Place, Vancouver, Canada','starts'=>'2026-06-14 04:00:00','ends'=>'2026-06-14 06:00:00'],
    ['uid'=>'wc-g-009','title'=>'Germany 7-1 Curacao','description'=>'Group E','location'=>'Houston Stadium, Houston, US','starts'=>'2026-06-14 17:00:00','ends'=>'2026-06-14 19:00:00'],
    ['uid'=>'wc-g-010','title'=>'Netherlands 2-2 Japan','description'=>'Group F','location'=>'Dallas Stadium, Dallas, US','starts'=>'2026-06-14 20:00:00','ends'=>'2026-06-14 22:00:00'],
    ['uid'=>'wc-g-011','title'=>'Ivory Coast 1-0 Ecuador','description'=>'Group E','location'=>'Philadelphia Stadium, Philadelphia, US','starts'=>'2026-06-14 23:00:00','ends'=>'2026-06-15 01:00:00'],
    ['uid'=>'wc-g-012','title'=>'Sweden 5-1 Tunisia','description'=>'Group F','location'=>'Estadio Monterrey, Guadalupe, Mexico','starts'=>'2026-06-15 02:00:00','ends'=>'2026-06-15 04:00:00'],
    ['uid'=>'wc-g-013','title'=>'Spain 0-0 Cape Verde','description'=>'Group H','location'=>'Atlanta Stadium, Atlanta, US','starts'=>'2026-06-15 16:00:00','ends'=>'2026-06-15 18:00:00'],
    ['uid'=>'wc-g-014','title'=>'Belgium 1-1 Egypt','description'=>'Group G','location'=>'BC Place, Vancouver, Canada','starts'=>'2026-06-15 19:00:00','ends'=>'2026-06-15 21:00:00'],
    ['uid'=>'wc-g-015','title'=>'Saudi Arabia 1-1 Uruguay','description'=>'Group H','location'=>'Miami Stadium, Miami, US','starts'=>'2026-06-15 22:00:00','ends'=>'2026-06-16 00:00:00'],
    ['uid'=>'wc-g-016','title'=>'Iran 2-2 New Zealand','description'=>'Group G','location'=>'Los Angeles Stadium, Los Angeles, US','starts'=>'2026-06-16 01:00:00','ends'=>'2026-06-16 03:00:00'],
    ['uid'=>'wc-g-017','title'=>'France 3-1 Senegal','description'=>'Group I','location'=>'New York New Jersey Stadium, New Jersey, US','starts'=>'2026-06-16 19:00:00','ends'=>'2026-06-16 21:00:00'],
    ['uid'=>'wc-g-018','title'=>'Iraq 1-4 Norway','description'=>'Group I','location'=>'Boston Stadium, Boston, US','starts'=>'2026-06-16 22:00:00','ends'=>'2026-06-17 00:00:00'],
    ['uid'=>'wc-g-019','title'=>'Argentina 3-0 Algeria','description'=>'Group J','location'=>'Kansas City Stadium, Kansas City, US','starts'=>'2026-06-17 01:00:00','ends'=>'2026-06-17 03:00:00'],
    ['uid'=>'wc-g-020','title'=>'Austria 3-1 Jordan','description'=>'Group J','location'=>'San Francisco Bay Area Stadium, San Francisco, US','starts'=>'2026-06-17 04:00:00','ends'=>'2026-06-17 06:00:00'],
    ['uid'=>'wc-g-021','title'=>'Portugal 1-1 DRC','description'=>'Group K','location'=>'Houston Stadium, Houston, US','starts'=>'2026-06-17 17:00:00','ends'=>'2026-06-17 19:00:00'],
    ['uid'=>'wc-g-022','title'=>'England 4-2 Croatia','description'=>'Group L','location'=>'Dallas Stadium, Dallas, US','starts'=>'2026-06-17 20:00:00','ends'=>'2026-06-17 22:00:00'],
    ['uid'=>'wc-g-023','title'=>'Ghana 1-0 Panama','description'=>'Group L','location'=>'Toronto Stadium, Toronto, Canada','starts'=>'2026-06-17 23:00:00','ends'=>'2026-06-18 01:00:00'],
    ['uid'=>'wc-g-024','title'=>'Uzbekistan 1-3 Colombia','description'=>'Group K','location'=>'Mexico City Stadium, Mexico City, Mexico','starts'=>'2026-06-18 02:00:00','ends'=>'2026-06-18 04:00:00'],
    ['uid'=>'wc-g-025','title'=>'Czechia 1-1 South Africa','description'=>'Group A','location'=>'Atlanta Stadium, Atlanta, US','starts'=>'2026-06-18 16:00:00','ends'=>'2026-06-18 18:00:00'],
    ['uid'=>'wc-g-026','title'=>'Switzerland 4-1 Bosnia','description'=>'Group B','location'=>'Los Angeles Stadium, Los Angeles, US','starts'=>'2026-06-18 19:00:00','ends'=>'2026-06-18 21:00:00'],
    ['uid'=>'wc-g-027','title'=>'Canada 6-0 Qatar','description'=>'Group B','location'=>'BC Place, Vancouver, Canada','starts'=>'2026-06-18 22:00:00','ends'=>'2026-06-19 00:00:00'],
    ['uid'=>'wc-g-028','title'=>'Mexico 1-0 South Korea','description'=>'Group A','location'=>'Estadio Guadalajara, Zapopan, Mexico','starts'=>'2026-06-19 01:00:00','ends'=>'2026-06-19 03:00:00'],
    ['uid'=>'wc-g-029','title'=>'Scotland 0-1 Morocco','description'=>'Group C','location'=>'Boston Stadium, Boston, US','starts'=>'2026-06-19 22:00:00','ends'=>'2026-06-20 00:00:00'],
    ['uid'=>'wc-g-030','title'=>'USA 2-0 Australia','description'=>'Group D','location'=>'Seattle Stadium, Seattle, US','starts'=>'2026-06-19 19:00:00','ends'=>'2026-06-19 21:00:00'],
    ['uid'=>'wc-g-031','title'=>'Brazil 3-0 Haiti','description'=>'Group C','location'=>'Philadelphia Stadium, Philadelphia, US','starts'=>'2026-06-20 00:30:00','ends'=>'2026-06-20 02:30:00'],
    ['uid'=>'wc-g-032','title'=>'Turkiye 0-1 Paraguay','description'=>'Group D','location'=>'San Francisco Bay Area Stadium, San Francisco, US','starts'=>'2026-06-20 03:00:00','ends'=>'2026-06-20 05:00:00'],
    ['uid'=>'wc-g-033','title'=>'Netherlands 5-1 Sweden','description'=>'Group F','location'=>'Houston Stadium, Houston, US','starts'=>'2026-06-20 17:00:00','ends'=>'2026-06-20 19:00:00'],
    ['uid'=>'wc-g-034','title'=>'Germany 2-1 Ivory Coast','description'=>'Group E','location'=>'Toronto Stadium, Toronto, Canada','starts'=>'2026-06-20 20:00:00','ends'=>'2026-06-20 22:00:00'],
    ['uid'=>'wc-g-035','title'=>'Ecuador 0-0 Curacao','description'=>'Group E','location'=>'Kansas City Stadium, Kansas City, US','starts'=>'2026-06-21 03:00:00','ends'=>'2026-06-21 05:00:00'],
    ['uid'=>'wc-g-036','title'=>'Tunisia 0-4 Japan','description'=>'Group F','location'=>'Estadio Monterrey, Guadalupe, Mexico','starts'=>'2026-06-21 04:00:00','ends'=>'2026-06-21 06:00:00'],
    ['uid'=>'wc-g-037','title'=>'Spain 4-0 Saudi Arabia','description'=>'Group H','location'=>'Atlanta Stadium, Atlanta, US','starts'=>'2026-06-21 16:00:00','ends'=>'2026-06-21 18:00:00'],
    ['uid'=>'wc-g-038','title'=>'Belgium 0-0 Iran','description'=>'Group G','location'=>'Los Angeles Stadium, Los Angeles, US','starts'=>'2026-06-21 19:00:00','ends'=>'2026-06-21 21:00:00'],
    ['uid'=>'wc-g-039','title'=>'Uruguay 2-2 Cape Verde','description'=>'Group H','location'=>'Miami Stadium, Miami, US','starts'=>'2026-06-21 22:00:00','ends'=>'2026-06-22 00:00:00'],
    ['uid'=>'wc-g-040','title'=>'New Zealand 1-3 Egypt','description'=>'Group G','location'=>'BC Place, Vancouver, Canada','starts'=>'2026-06-22 01:00:00','ends'=>'2026-06-22 03:00:00'],
    ['uid'=>'wc-g-041','title'=>'Argentina 2-0 Austria','description'=>'Group J','location'=>'Dallas Stadium, Dallas, US','starts'=>'2026-06-22 17:00:00','ends'=>'2026-06-22 19:00:00'],
    ['uid'=>'wc-g-042','title'=>'France 3-0 Iraq','description'=>'Group I','location'=>'Philadelphia Stadium, Philadelphia, US','starts'=>'2026-06-22 21:00:00','ends'=>'2026-06-22 23:00:00'],
    ['uid'=>'wc-g-043','title'=>'Norway 3-2 Senegal','description'=>'Group I','location'=>'New York New Jersey Stadium, New Jersey, US','starts'=>'2026-06-23 00:00:00','ends'=>'2026-06-23 02:00:00'],
    ['uid'=>'wc-g-044','title'=>'Jordan 1-2 Algeria','description'=>'Group J','location'=>'San Francisco Bay Area Stadium, San Francisco, US','starts'=>'2026-06-23 03:00:00','ends'=>'2026-06-23 05:00:00'],
    ['uid'=>'wc-g-045','title'=>'Portugal 5-0 Uzbekistan','description'=>'Group K','location'=>'Houston Stadium, Houston, US','starts'=>'2026-06-23 17:00:00','ends'=>'2026-06-23 19:00:00'],
    ['uid'=>'wc-g-046','title'=>'England 0-0 Ghana','description'=>'Group L','location'=>'Boston Stadium, Boston, US','starts'=>'2026-06-23 20:00:00','ends'=>'2026-06-23 22:00:00'],
    ['uid'=>'wc-g-047','title'=>'Panama 0-1 Croatia','description'=>'Group L','location'=>'Toronto Stadium, Toronto, Canada','starts'=>'2026-06-23 23:00:00','ends'=>'2026-06-24 01:00:00'],
    ['uid'=>'wc-g-048','title'=>'Colombia 1-0 DRC','description'=>'Group K','location'=>'Estadio Guadalajara, Zapopan, Mexico','starts'=>'2026-06-24 02:00:00','ends'=>'2026-06-24 04:00:00'],
    ['uid'=>'wc-g-049','title'=>'Switzerland 2-1 Canada','description'=>'Group B','location'=>'BC Place, Vancouver, Canada','starts'=>'2026-06-24 19:00:00','ends'=>'2026-06-24 21:00:00'],
    ['uid'=>'wc-g-050','title'=>'Bosnia 3-1 Qatar','description'=>'Group B','location'=>'Seattle Stadium, Seattle, US','starts'=>'2026-06-24 19:00:00','ends'=>'2026-06-24 21:00:00'],
    ['uid'=>'wc-g-051','title'=>'Scotland 0-3 Brazil','description'=>'Group C','location'=>'Miami Stadium, Miami, US','starts'=>'2026-06-24 22:00:00','ends'=>'2026-06-25 00:00:00'],
    ['uid'=>'wc-g-052','title'=>'Morocco 4-2 Haiti','description'=>'Group C','location'=>'Atlanta Stadium, Atlanta, US','starts'=>'2026-06-24 22:00:00','ends'=>'2026-06-25 00:00:00'],
    ['uid'=>'wc-g-053','title'=>'Czechia 0-3 Mexico','description'=>'Group A','location'=>'Mexico City Stadium, Mexico City, Mexico','starts'=>'2026-06-25 01:00:00','ends'=>'2026-06-25 03:00:00'],
    ['uid'=>'wc-g-054','title'=>'South Africa 1-0 South Korea','description'=>'Group A','location'=>'Estadio Monterrey, Guadalupe, Mexico','starts'=>'2026-06-25 01:00:00','ends'=>'2026-06-25 03:00:00'],
    ['uid'=>'wc-g-055','title'=>'Ecuador 2-1 Germany','description'=>'Group E','location'=>'New York New Jersey Stadium, New Jersey, US','starts'=>'2026-06-25 20:00:00','ends'=>'2026-06-25 22:00:00'],
    ['uid'=>'wc-g-056','title'=>'Curacao 0-2 Ivory Coast','description'=>'Group E','location'=>'Philadelphia Stadium, Philadelphia, US','starts'=>'2026-06-25 20:00:00','ends'=>'2026-06-25 22:00:00'],
    ['uid'=>'wc-g-057','title'=>'Japan 1-1 Sweden','description'=>'Group F','location'=>'Dallas Stadium, Dallas, US','starts'=>'2026-06-25 23:00:00','ends'=>'2026-06-26 01:00:00'],
    ['uid'=>'wc-g-058','title'=>'Tunisia 1-3 Netherlands','description'=>'Group F','location'=>'Kansas City Stadium, Kansas City, US','starts'=>'2026-06-25 23:00:00','ends'=>'2026-06-26 01:00:00'],
    ['uid'=>'wc-g-059','title'=>'Turkiye 3-2 USA','description'=>'Group D','location'=>'Los Angeles Stadium, Los Angeles, US','starts'=>'2026-06-26 02:00:00','ends'=>'2026-06-26 04:00:00'],
    ['uid'=>'wc-g-060','title'=>'Paraguay 0-0 Australia','description'=>'Group D','location'=>'San Francisco Bay Area Stadium, San Francisco, US','starts'=>'2026-06-26 02:00:00','ends'=>'2026-06-26 04:00:00'],
    ['uid'=>'wc-g-061','title'=>'Norway 1-4 France','description'=>'Group I','location'=>'Boston Stadium, Boston, US','starts'=>'2026-06-26 19:00:00','ends'=>'2026-06-26 21:00:00'],
    ['uid'=>'wc-g-062','title'=>'Senegal 5-0 Iraq','description'=>'Group I','location'=>'Toronto Stadium, Toronto, Canada','starts'=>'2026-06-26 19:00:00','ends'=>'2026-06-26 21:00:00'],
    ['uid'=>'wc-g-063','title'=>'Cape Verde 0-0 Saudi Arabia','description'=>'Group H','location'=>'Houston Stadium, Houston, US','starts'=>'2026-06-27 00:00:00','ends'=>'2026-06-27 02:00:00'],
    ['uid'=>'wc-g-064','title'=>'Uruguay 0-1 Spain','description'=>'Group H','location'=>'Estadio Guadalajara, Zapopan, Mexico','starts'=>'2026-06-27 00:00:00','ends'=>'2026-06-27 02:00:00'],
    ['uid'=>'wc-g-065','title'=>'Egypt 1-1 Iran','description'=>'Group G','location'=>'Seattle Stadium, Seattle, US','starts'=>'2026-06-27 03:00:00','ends'=>'2026-06-27 05:00:00'],
    ['uid'=>'wc-g-066','title'=>'New Zealand 1-5 Belgium','description'=>'Group G','location'=>'BC Place, Vancouver, Canada','starts'=>'2026-06-27 03:00:00','ends'=>'2026-06-27 05:00:00'],
    ['uid'=>'wc-g-067','title'=>'Panama 0-2 England','description'=>'Group L','location'=>'New York New Jersey Stadium, New Jersey, US','starts'=>'2026-06-27 21:00:00','ends'=>'2026-06-27 23:00:00'],
    ['uid'=>'wc-g-068','title'=>'Croatia 2-1 Ghana','description'=>'Group L','location'=>'Philadelphia Stadium, Philadelphia, US','starts'=>'2026-06-27 21:00:00','ends'=>'2026-06-27 23:00:00'],
    ['uid'=>'wc-g-069','title'=>'Colombia 0-0 Portugal','description'=>'Group K','location'=>'Miami Stadium, Miami, US','starts'=>'2026-06-27 23:30:00','ends'=>'2026-06-28 01:30:00'],
    ['uid'=>'wc-g-070','title'=>'DRC 3-1 Uzbekistan','description'=>'Group K','location'=>'Atlanta Stadium, Atlanta, US','starts'=>'2026-06-27 23:30:00','ends'=>'2026-06-28 01:30:00'],
    ['uid'=>'wc-g-071','title'=>'Algeria 3-3 Austria','description'=>'Group J','location'=>'Kansas City Stadium, Kansas City, US','starts'=>'2026-06-28 02:00:00','ends'=>'2026-06-28 04:00:00'],
    ['uid'=>'wc-g-072','title'=>'Jordan 1-3 Argentina','description'=>'Group J','location'=>'Dallas Stadium, Dallas, US','starts'=>'2026-06-28 02:00:00','ends'=>'2026-06-28 04:00:00'],
    // ---- Round of 32 (played matches show the score in the title) ----
    ['uid'=>'wc-ko-01','title'=>'South Africa 0-1 Canada','description'=>'Round of 32','location'=>'Los Angeles Stadium, Los Angeles, US','starts'=>'2026-06-28 19:00:00','ends'=>'2026-06-28 21:00:00'],
    ['uid'=>'wc-ko-02','title'=>'Brazil 2-1 Japan','description'=>'Round of 32','location'=>'Houston Stadium, Houston, US','starts'=>'2026-06-29 17:00:00','ends'=>'2026-06-29 19:00:00'],
    ['uid'=>'wc-ko-03','title'=>'Germany 1-1 Paraguay (Paraguay 4-3 pens)','description'=>'Round of 32','location'=>'Boston Stadium, Boston, US','starts'=>'2026-06-29 20:30:00','ends'=>'2026-06-29 22:30:00'],
    ['uid'=>'wc-ko-04','title'=>'Netherlands 1-1 Morocco (Morocco 3-2 pens)','description'=>'Round of 32','location'=>'Estadio Monterrey, Guadalupe, Mexico','starts'=>'2026-06-30 01:00:00','ends'=>'2026-06-30 03:00:00'],
    ['uid'=>'wc-ko-05','title'=>'Ivory Coast 1-2 Norway','description'=>'Round of 32','location'=>'Dallas Stadium, Dallas, US','starts'=>'2026-06-30 17:00:00','ends'=>'2026-06-30 19:00:00'],
    ['uid'=>'wc-ko-06','title'=>'France 3-0 Sweden','description'=>'Round of 32','location'=>'New York New Jersey Stadium, New Jersey, US','starts'=>'2026-06-30 21:00:00','ends'=>'2026-06-30 23:00:00'],
    ['uid'=>'wc-ko-07','title'=>'Mexico 2-0 Ecuador','description'=>'Round of 32','location'=>'Mexico City Stadium, Mexico City, Mexico','starts'=>'2026-07-01 01:00:00','ends'=>'2026-07-01 03:00:00'],
    ['uid'=>'wc-ko-08','title'=>'England 2-1 DR Congo','description'=>'Round of 32','location'=>'Atlanta Stadium, Atlanta, US','starts'=>'2026-07-01 16:00:00','ends'=>'2026-07-01 18:00:00'],
    ['uid'=>'wc-ko-09','title'=>'Belgium vs Senegal','description'=>'Round of 32','location'=>'Seattle Stadium, Seattle, US','starts'=>'2026-07-01 20:00:00','ends'=>'2026-07-01 22:00:00'],
    ['uid'=>'wc-ko-10','title'=>'USA vs Bosnia','description'=>'Round of 32','location'=>'San Francisco Bay Area Stadium, San Francisco, US','starts'=>'2026-07-02 00:00:00','ends'=>'2026-07-02 02:00:00'],
    ['uid'=>'wc-ko-11','title'=>'Spain vs Austria','description'=>'Round of 32','location'=>'Los Angeles Stadium, Los Angeles, US','starts'=>'2026-07-02 19:00:00','ends'=>'2026-07-02 21:00:00'],
    ['uid'=>'wc-ko-12','title'=>'Portugal vs Croatia','description'=>'Round of 32','location'=>'Toronto Stadium, Toronto, Canada','starts'=>'2026-07-02 23:00:00','ends'=>'2026-07-03 01:00:00'],
    ['uid'=>'wc-ko-13','title'=>'Switzerland vs Algeria','description'=>'Round of 32','location'=>'BC Place, Vancouver, Canada','starts'=>'2026-07-03 03:00:00','ends'=>'2026-07-03 05:00:00'],
    ['uid'=>'wc-ko-14','title'=>'Australia vs Egypt','description'=>'Round of 32','location'=>'Dallas Stadium, Dallas, US','starts'=>'2026-07-03 18:00:00','ends'=>'2026-07-03 20:00:00'],
    ['uid'=>'wc-ko-15','title'=>'Argentina vs Cape Verde','description'=>'Round of 32','location'=>'Miami Stadium, Miami, US','starts'=>'2026-07-03 22:00:00','ends'=>'2026-07-04 00:00:00'],
    ['uid'=>'wc-ko-16','title'=>'Colombia vs Ghana','description'=>'Round of 32','location'=>'Kansas City Stadium, Kansas City, US','starts'=>'2026-07-04 01:30:00','ends'=>'2026-07-04 03:30:00'],
    // ---- Round of 16 (matchups fill in as Round-of-32 ties finish) ----
    ['uid'=>'wc-ko-17','title'=>'Canada vs Morocco','description'=>'Round of 16','location'=>'Houston Stadium, Houston, US','starts'=>'2026-07-04 17:00:00','ends'=>'2026-07-04 19:00:00'],
    ['uid'=>'wc-ko-18','title'=>'Paraguay vs France','description'=>'Round of 16','location'=>'Philadelphia Stadium, Philadelphia, US','starts'=>'2026-07-04 21:00:00','ends'=>'2026-07-04 23:00:00'],
    ['uid'=>'wc-ko-19','title'=>'Brazil vs Norway','description'=>'Round of 16','location'=>'New York New Jersey Stadium, New Jersey, US','starts'=>'2026-07-05 20:00:00','ends'=>'2026-07-05 22:00:00'],
    ['uid'=>'wc-ko-20','title'=>'Mexico vs England','description'=>'Round of 16','location'=>'Mexico City Stadium, Mexico City, Mexico','starts'=>'2026-07-06 00:00:00','ends'=>'2026-07-06 02:00:00'],
    ['uid'=>'wc-ko-21','title'=>'Round of 16 — Match 5','description'=>'Round of 16 — Winner Portugal/Croatia vs Winner Spain/Austria','location'=>'Dallas Stadium, Dallas, US','starts'=>'2026-07-06 19:00:00','ends'=>'2026-07-06 21:00:00'],
    ['uid'=>'wc-ko-22','title'=>'Round of 16 — Match 6','description'=>'Round of 16 — Winner USA/Bosnia vs Winner Belgium/Senegal','location'=>'Seattle Stadium, Seattle, US','starts'=>'2026-07-07 00:00:00','ends'=>'2026-07-07 02:00:00'],
    ['uid'=>'wc-ko-23','title'=>'Round of 16 — Match 7','description'=>'Round of 16 — Winner Argentina/Cape Verde vs Winner Australia/Egypt','location'=>'Atlanta Stadium, Atlanta, US','starts'=>'2026-07-07 16:00:00','ends'=>'2026-07-07 18:00:00'],
    ['uid'=>'wc-ko-24','title'=>'Round of 16 — Match 8','description'=>'Round of 16 — Winner Switzerland/Algeria vs Winner Colombia/Ghana','location'=>'BC Place, Vancouver, Canada','starts'=>'2026-07-07 20:00:00','ends'=>'2026-07-07 22:00:00'],
    ['uid'=>'wc-ko-25','title'=>'Quarterfinal — First','location'=>'Boston Stadium, Boston, US','starts'=>'2026-07-09 20:00:00','ends'=>'2026-07-09 22:00:00'],
    ['uid'=>'wc-ko-26','title'=>'Quarterfinal — Second','location'=>'Los Angeles Stadium, Los Angeles, US','starts'=>'2026-07-10 19:00:00','ends'=>'2026-07-10 21:00:00'],
    ['uid'=>'wc-ko-27','title'=>'Quarterfinal — Third','location'=>'Miami Stadium, Miami, US','starts'=>'2026-07-11 21:00:00','ends'=>'2026-07-11 23:00:00'],
    ['uid'=>'wc-ko-28','title'=>'Quarterfinal — Fourth','location'=>'Kansas City Stadium, Kansas City, US','starts'=>'2026-07-12 01:00:00','ends'=>'2026-07-12 03:00:00'],
    ['uid'=>'wc-ko-29','title'=>'Semifinal — First','location'=>'Dallas Stadium, Dallas, US','starts'=>'2026-07-14 19:00:00','ends'=>'2026-07-14 21:00:00'],
    ['uid'=>'wc-ko-30','title'=>'Semifinal — Second','location'=>'Atlanta Stadium, Atlanta, US','starts'=>'2026-07-15 19:00:00','ends'=>'2026-07-15 21:00:00'],
    ['uid'=>'wc-ko-31','title'=>'Bronze Medal Match','location'=>'Miami Stadium, Miami, US','starts'=>'2026-07-18 21:00:00','ends'=>'2026-07-18 23:00:00'],
    ['uid'=>'wc-ko-32','title'=>'Final','location'=>'New York New Jersey Stadium, New Jersey, US','starts'=>'2026-07-19 19:00:00','ends'=>'2026-07-19 21:00:00'],
];
foreach ($wc_matches as $e) { upsert_event($pdo, $wc, $e); }

header('Location: /?seeded=1');
exit;
