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
// Group-stage final scores + knockout bracket updated after Matchday 3 and the
// first Round-of-32 results (as of 2026-07-01). Sources: FourFourTwo, NBC Sports,
// World Cup Wiki, Wikipedia. Score orientation matches each event's title order.
$wc = upsert_calendar($pdo, [
    'slug' => 'world-cup', 'name' => '2026 World Cup', 'color' => '#22c55e', 'icon' => '⚽',
    'priority' => 20, 'description' => 'FIFA World Cup 2026 — all 104 matches across USA, Canada, Mexico. Kickoff times shown in your local timezone.',
]);
// drop any stale World Cup sample events
$pdo->prepare("DELETE FROM events WHERE calendar_id = ? AND uid NOT LIKE 'wc-g-%' AND uid NOT LIKE 'wc-ko-%'")->execute([$wc]);

$wc_matches = [
    ['uid'=>'wc-g-001','title'=>'Mexico vs South Africa','description'=>'Group A — Full-time: Mexico 2-0 South Africa','location'=>'Mexico City Stadium, Mexico City, Mexico','starts'=>'2026-06-11 19:00:00','ends'=>'2026-06-11 21:00:00'],
    ['uid'=>'wc-g-002','title'=>'South Korea vs Czechia','description'=>'Group A — Full-time: South Korea 2-1 Czechia','location'=>'Estadio Guadalajara, Zapopan, Mexico','starts'=>'2026-06-12 02:00:00','ends'=>'2026-06-12 04:00:00'],
    ['uid'=>'wc-g-003','title'=>'Canada vs Bosnia','description'=>'Group B — Full-time: Canada 1-1 Bosnia','location'=>'Toronto Stadium, Toronto, Canada','starts'=>'2026-06-12 19:00:00','ends'=>'2026-06-12 21:00:00'],
    ['uid'=>'wc-g-004','title'=>'USA vs Paraguay','description'=>'Group D — Full-time: USA 4-1 Paraguay','location'=>'Los Angeles Stadium, Los Angeles, US','starts'=>'2026-06-13 01:00:00','ends'=>'2026-06-13 03:00:00'],
    ['uid'=>'wc-g-005','title'=>'Qatar vs Switzerland','description'=>'Group B — Full-time: Qatar 1-1 Switzerland','location'=>'San Francisco Bay Area Stadium, San Francisco, US','starts'=>'2026-06-13 19:00:00','ends'=>'2026-06-13 21:00:00'],
    ['uid'=>'wc-g-006','title'=>'Brazil vs Morocco','description'=>'Group C — Full-time: Brazil 1-1 Morocco','location'=>'New York New Jersey Stadium, New Jersey, US','starts'=>'2026-06-13 22:00:00','ends'=>'2026-06-14 00:00:00'],
    ['uid'=>'wc-g-007','title'=>'Haiti vs Scotland','description'=>'Group C — Full-time: Haiti 0-1 Scotland','location'=>'Boston Stadium, Boston, US','starts'=>'2026-06-14 01:00:00','ends'=>'2026-06-14 03:00:00'],
    ['uid'=>'wc-g-008','title'=>'Australia vs Turkiye','description'=>'Group D — Full-time: Australia 2-0 Turkiye','location'=>'BC Place, Vancouver, Canada','starts'=>'2026-06-14 04:00:00','ends'=>'2026-06-14 06:00:00'],
    ['uid'=>'wc-g-009','title'=>'Germany vs Curacao','description'=>'Group E — Full-time: Germany 7-1 Curacao','location'=>'Houston Stadium, Houston, US','starts'=>'2026-06-14 17:00:00','ends'=>'2026-06-14 19:00:00'],
    ['uid'=>'wc-g-010','title'=>'Netherlands vs Japan','description'=>'Group F — Full-time: Netherlands 2-2 Japan','location'=>'Dallas Stadium, Dallas, US','starts'=>'2026-06-14 20:00:00','ends'=>'2026-06-14 22:00:00'],
    ['uid'=>'wc-g-011','title'=>'Ivory Coast vs Ecuador','description'=>'Group E — Full-time: Ivory Coast 1-0 Ecuador','location'=>'Philadelphia Stadium, Philadelphia, US','starts'=>'2026-06-14 23:00:00','ends'=>'2026-06-15 01:00:00'],
    ['uid'=>'wc-g-012','title'=>'Sweden vs Tunisia','description'=>'Group F — Full-time: Sweden 5-1 Tunisia','location'=>'Estadio Monterrey, Guadalupe, Mexico','starts'=>'2026-06-15 02:00:00','ends'=>'2026-06-15 04:00:00'],
    ['uid'=>'wc-g-013','title'=>'Spain vs Cape Verde','description'=>'Group H — Full-time: Spain 0-0 Cape Verde','location'=>'Atlanta Stadium, Atlanta, US','starts'=>'2026-06-15 16:00:00','ends'=>'2026-06-15 18:00:00'],
    ['uid'=>'wc-g-014','title'=>'Belgium vs Egypt','description'=>'Group G — Full-time: Belgium 1-1 Egypt','location'=>'BC Place, Vancouver, Canada','starts'=>'2026-06-15 19:00:00','ends'=>'2026-06-15 21:00:00'],
    ['uid'=>'wc-g-015','title'=>'Saudi Arabia vs Uruguay','description'=>'Group H — Full-time: Saudi Arabia 1-1 Uruguay','location'=>'Miami Stadium, Miami, US','starts'=>'2026-06-15 22:00:00','ends'=>'2026-06-16 00:00:00'],
    ['uid'=>'wc-g-016','title'=>'Iran vs New Zealand','description'=>'Group G — Full-time: Iran 2-2 New Zealand','location'=>'Los Angeles Stadium, Los Angeles, US','starts'=>'2026-06-16 01:00:00','ends'=>'2026-06-16 03:00:00'],
    ['uid'=>'wc-g-017','title'=>'France vs Senegal','description'=>'Group I — Full-time: France 3-1 Senegal','location'=>'New York New Jersey Stadium, New Jersey, US','starts'=>'2026-06-16 19:00:00','ends'=>'2026-06-16 21:00:00'],
    ['uid'=>'wc-g-018','title'=>'Iraq vs Norway','description'=>'Group I — Full-time: Iraq 1-4 Norway','location'=>'Boston Stadium, Boston, US','starts'=>'2026-06-16 22:00:00','ends'=>'2026-06-17 00:00:00'],
    ['uid'=>'wc-g-019','title'=>'Argentina vs Algeria','description'=>'Group J — Full-time: Argentina 3-0 Algeria','location'=>'Kansas City Stadium, Kansas City, US','starts'=>'2026-06-17 01:00:00','ends'=>'2026-06-17 03:00:00'],
    ['uid'=>'wc-g-020','title'=>'Austria vs Jordan','description'=>'Group J — Full-time: Austria 3-1 Jordan','location'=>'San Francisco Bay Area Stadium, San Francisco, US','starts'=>'2026-06-17 04:00:00','ends'=>'2026-06-17 06:00:00'],
    ['uid'=>'wc-g-021','title'=>'Portugal vs DRC','description'=>'Group K — Full-time: Portugal 1-1 DRC','location'=>'Houston Stadium, Houston, US','starts'=>'2026-06-17 17:00:00','ends'=>'2026-06-17 19:00:00'],
    ['uid'=>'wc-g-022','title'=>'England vs Croatia','description'=>'Group L — Full-time: England 4-2 Croatia','location'=>'Dallas Stadium, Dallas, US','starts'=>'2026-06-17 20:00:00','ends'=>'2026-06-17 22:00:00'],
    ['uid'=>'wc-g-023','title'=>'Ghana vs Panama','description'=>'Group L — Full-time: Ghana 1-0 Panama','location'=>'Toronto Stadium, Toronto, Canada','starts'=>'2026-06-17 23:00:00','ends'=>'2026-06-18 01:00:00'],
    ['uid'=>'wc-g-024','title'=>'Uzbekistan vs Colombia','description'=>'Group K — Full-time: Uzbekistan 1-3 Colombia','location'=>'Mexico City Stadium, Mexico City, Mexico','starts'=>'2026-06-18 02:00:00','ends'=>'2026-06-18 04:00:00'],
    ['uid'=>'wc-g-025','title'=>'Czechia vs South Africa','description'=>'Group A — Full-time: Czechia 1-1 South Africa','location'=>'Atlanta Stadium, Atlanta, US','starts'=>'2026-06-18 16:00:00','ends'=>'2026-06-18 18:00:00'],
    ['uid'=>'wc-g-026','title'=>'Switzerland vs Bosnia','description'=>'Group B — Full-time: Switzerland 4-1 Bosnia','location'=>'Los Angeles Stadium, Los Angeles, US','starts'=>'2026-06-18 19:00:00','ends'=>'2026-06-18 21:00:00'],
    ['uid'=>'wc-g-027','title'=>'Canada vs Qatar','description'=>'Group B — Full-time: Canada 6-0 Qatar','location'=>'BC Place, Vancouver, Canada','starts'=>'2026-06-18 22:00:00','ends'=>'2026-06-19 00:00:00'],
    ['uid'=>'wc-g-028','title'=>'Mexico vs South Korea','description'=>'Group A — Full-time: Mexico 1-0 South Korea','location'=>'Estadio Guadalajara, Zapopan, Mexico','starts'=>'2026-06-19 01:00:00','ends'=>'2026-06-19 03:00:00'],
    ['uid'=>'wc-g-029','title'=>'Scotland vs Morocco','description'=>'Group C — Full-time: Scotland 0-1 Morocco','location'=>'Boston Stadium, Boston, US','starts'=>'2026-06-19 22:00:00','ends'=>'2026-06-20 00:00:00'],
    ['uid'=>'wc-g-030','title'=>'USA vs Australia','description'=>'Group D — Full-time: USA 2-0 Australia','location'=>'Seattle Stadium, Seattle, US','starts'=>'2026-06-19 19:00:00','ends'=>'2026-06-19 21:00:00'],
    ['uid'=>'wc-g-031','title'=>'Brazil vs Haiti','description'=>'Group C — Full-time: Brazil 3-0 Haiti','location'=>'Philadelphia Stadium, Philadelphia, US','starts'=>'2026-06-20 00:30:00','ends'=>'2026-06-20 02:30:00'],
    ['uid'=>'wc-g-032','title'=>'Turkiye vs Paraguay','description'=>'Group D — Full-time: Turkiye 0-1 Paraguay','location'=>'San Francisco Bay Area Stadium, San Francisco, US','starts'=>'2026-06-20 03:00:00','ends'=>'2026-06-20 05:00:00'],
    ['uid'=>'wc-g-033','title'=>'Netherlands vs Sweden','description'=>'Group F — Full-time: Netherlands 5-1 Sweden','location'=>'Houston Stadium, Houston, US','starts'=>'2026-06-20 17:00:00','ends'=>'2026-06-20 19:00:00'],
    ['uid'=>'wc-g-034','title'=>'Germany vs Ivory Coast','description'=>'Group E — Full-time: Germany 2-1 Ivory Coast','location'=>'Toronto Stadium, Toronto, Canada','starts'=>'2026-06-20 20:00:00','ends'=>'2026-06-20 22:00:00'],
    ['uid'=>'wc-g-035','title'=>'Ecuador vs Curacao','description'=>'Group E — Full-time: Ecuador 0-0 Curacao','location'=>'Kansas City Stadium, Kansas City, US','starts'=>'2026-06-21 03:00:00','ends'=>'2026-06-21 05:00:00'],
    ['uid'=>'wc-g-036','title'=>'Tunisia vs Japan','description'=>'Group F — Full-time: Tunisia 0-4 Japan','location'=>'Estadio Monterrey, Guadalupe, Mexico','starts'=>'2026-06-21 04:00:00','ends'=>'2026-06-21 06:00:00'],
    ['uid'=>'wc-g-037','title'=>'Spain vs Saudi Arabia','description'=>'Group H — Full-time: Spain 4-0 Saudi Arabia','location'=>'Atlanta Stadium, Atlanta, US','starts'=>'2026-06-21 16:00:00','ends'=>'2026-06-21 18:00:00'],
    ['uid'=>'wc-g-038','title'=>'Belgium vs Iran','description'=>'Group G — Full-time: Belgium 0-0 Iran','location'=>'Los Angeles Stadium, Los Angeles, US','starts'=>'2026-06-21 19:00:00','ends'=>'2026-06-21 21:00:00'],
    ['uid'=>'wc-g-039','title'=>'Uruguay vs Cape Verde','description'=>'Group H — Full-time: Uruguay 2-2 Cape Verde','location'=>'Miami Stadium, Miami, US','starts'=>'2026-06-21 22:00:00','ends'=>'2026-06-22 00:00:00'],
    ['uid'=>'wc-g-040','title'=>'New Zealand vs Egypt','description'=>'Group G — Full-time: New Zealand 1-3 Egypt','location'=>'BC Place, Vancouver, Canada','starts'=>'2026-06-22 01:00:00','ends'=>'2026-06-22 03:00:00'],
    ['uid'=>'wc-g-041','title'=>'Argentina vs Austria','description'=>'Group J — Full-time: Argentina 2-0 Austria','location'=>'Dallas Stadium, Dallas, US','starts'=>'2026-06-22 17:00:00','ends'=>'2026-06-22 19:00:00'],
    ['uid'=>'wc-g-042','title'=>'France vs Iraq','description'=>'Group I — Full-time: France 3-0 Iraq','location'=>'Philadelphia Stadium, Philadelphia, US','starts'=>'2026-06-22 21:00:00','ends'=>'2026-06-22 23:00:00'],
    ['uid'=>'wc-g-043','title'=>'Norway vs Senegal','description'=>'Group I — Full-time: Norway 3-2 Senegal','location'=>'New York New Jersey Stadium, New Jersey, US','starts'=>'2026-06-23 00:00:00','ends'=>'2026-06-23 02:00:00'],
    ['uid'=>'wc-g-044','title'=>'Jordan vs Algeria','description'=>'Group J — Full-time: Jordan 1-2 Algeria','location'=>'San Francisco Bay Area Stadium, San Francisco, US','starts'=>'2026-06-23 03:00:00','ends'=>'2026-06-23 05:00:00'],
    ['uid'=>'wc-g-045','title'=>'Portugal vs Uzbekistan','description'=>'Group K — Full-time: Portugal 5-0 Uzbekistan','location'=>'Houston Stadium, Houston, US','starts'=>'2026-06-23 17:00:00','ends'=>'2026-06-23 19:00:00'],
    ['uid'=>'wc-g-046','title'=>'England vs Ghana','description'=>'Group L — Full-time: England 0-0 Ghana','location'=>'Boston Stadium, Boston, US','starts'=>'2026-06-23 20:00:00','ends'=>'2026-06-23 22:00:00'],
    ['uid'=>'wc-g-047','title'=>'Panama vs Croatia','description'=>'Group L — Full-time: Panama 0-1 Croatia','location'=>'Toronto Stadium, Toronto, Canada','starts'=>'2026-06-23 23:00:00','ends'=>'2026-06-24 01:00:00'],
    ['uid'=>'wc-g-048','title'=>'Colombia vs DRC','description'=>'Group K — Full-time: Colombia 1-0 DRC','location'=>'Estadio Guadalajara, Zapopan, Mexico','starts'=>'2026-06-24 02:00:00','ends'=>'2026-06-24 04:00:00'],
    ['uid'=>'wc-g-049','title'=>'Switzerland vs Canada','description'=>'Group B — Full-time: Switzerland 2-1 Canada','location'=>'BC Place, Vancouver, Canada','starts'=>'2026-06-24 19:00:00','ends'=>'2026-06-24 21:00:00'],
    ['uid'=>'wc-g-050','title'=>'Bosnia vs Qatar','description'=>'Group B — Full-time: Bosnia 3-1 Qatar','location'=>'Seattle Stadium, Seattle, US','starts'=>'2026-06-24 19:00:00','ends'=>'2026-06-24 21:00:00'],
    ['uid'=>'wc-g-051','title'=>'Scotland vs Brazil','description'=>'Group C — Full-time: Scotland 0-3 Brazil','location'=>'Miami Stadium, Miami, US','starts'=>'2026-06-24 22:00:00','ends'=>'2026-06-25 00:00:00'],
    ['uid'=>'wc-g-052','title'=>'Morocco vs Haiti','description'=>'Group C — Full-time: Morocco 4-2 Haiti','location'=>'Atlanta Stadium, Atlanta, US','starts'=>'2026-06-24 22:00:00','ends'=>'2026-06-25 00:00:00'],
    ['uid'=>'wc-g-053','title'=>'Czechia vs Mexico','description'=>'Group A — Full-time: Czechia 0-3 Mexico','location'=>'Mexico City Stadium, Mexico City, Mexico','starts'=>'2026-06-25 01:00:00','ends'=>'2026-06-25 03:00:00'],
    ['uid'=>'wc-g-054','title'=>'South Africa vs South Korea','description'=>'Group A — Full-time: South Africa 1-0 South Korea','location'=>'Estadio Monterrey, Guadalupe, Mexico','starts'=>'2026-06-25 01:00:00','ends'=>'2026-06-25 03:00:00'],
    ['uid'=>'wc-g-055','title'=>'Ecuador vs Germany','description'=>'Group E — Full-time: Ecuador 2-1 Germany','location'=>'New York New Jersey Stadium, New Jersey, US','starts'=>'2026-06-25 20:00:00','ends'=>'2026-06-25 22:00:00'],
    ['uid'=>'wc-g-056','title'=>'Curacao vs Ivory Coast','description'=>'Group E — Full-time: Curacao 0-2 Ivory Coast','location'=>'Philadelphia Stadium, Philadelphia, US','starts'=>'2026-06-25 20:00:00','ends'=>'2026-06-25 22:00:00'],
    ['uid'=>'wc-g-057','title'=>'Japan vs Sweden','description'=>'Group F — Full-time: Japan 1-1 Sweden','location'=>'Dallas Stadium, Dallas, US','starts'=>'2026-06-25 23:00:00','ends'=>'2026-06-26 01:00:00'],
    ['uid'=>'wc-g-058','title'=>'Tunisia vs Netherlands','description'=>'Group F — Full-time: Tunisia 1-3 Netherlands','location'=>'Kansas City Stadium, Kansas City, US','starts'=>'2026-06-25 23:00:00','ends'=>'2026-06-26 01:00:00'],
    ['uid'=>'wc-g-059','title'=>'Turkiye vs USA','description'=>'Group D — Full-time: Turkiye 3-2 USA','location'=>'Los Angeles Stadium, Los Angeles, US','starts'=>'2026-06-26 02:00:00','ends'=>'2026-06-26 04:00:00'],
    ['uid'=>'wc-g-060','title'=>'Paraguay vs Australia','description'=>'Group D — Full-time: Paraguay 0-0 Australia','location'=>'San Francisco Bay Area Stadium, San Francisco, US','starts'=>'2026-06-26 02:00:00','ends'=>'2026-06-26 04:00:00'],
    ['uid'=>'wc-g-061','title'=>'Norway vs France','description'=>'Group I — Full-time: Norway 1-4 France','location'=>'Boston Stadium, Boston, US','starts'=>'2026-06-26 19:00:00','ends'=>'2026-06-26 21:00:00'],
    ['uid'=>'wc-g-062','title'=>'Senegal vs Iraq','description'=>'Group I — Full-time: Senegal 5-0 Iraq','location'=>'Toronto Stadium, Toronto, Canada','starts'=>'2026-06-26 19:00:00','ends'=>'2026-06-26 21:00:00'],
    ['uid'=>'wc-g-063','title'=>'Cape Verde vs Saudi Arabia','description'=>'Group H — Full-time: Cape Verde 0-0 Saudi Arabia','location'=>'Houston Stadium, Houston, US','starts'=>'2026-06-27 00:00:00','ends'=>'2026-06-27 02:00:00'],
    ['uid'=>'wc-g-064','title'=>'Uruguay vs Spain','description'=>'Group H — Full-time: Uruguay 0-1 Spain','location'=>'Estadio Guadalajara, Zapopan, Mexico','starts'=>'2026-06-27 00:00:00','ends'=>'2026-06-27 02:00:00'],
    ['uid'=>'wc-g-065','title'=>'Egypt vs Iran','description'=>'Group G — Full-time: Egypt 1-1 Iran','location'=>'Seattle Stadium, Seattle, US','starts'=>'2026-06-27 03:00:00','ends'=>'2026-06-27 05:00:00'],
    ['uid'=>'wc-g-066','title'=>'New Zealand vs Belgium','description'=>'Group G — Full-time: New Zealand 1-5 Belgium','location'=>'BC Place, Vancouver, Canada','starts'=>'2026-06-27 03:00:00','ends'=>'2026-06-27 05:00:00'],
    ['uid'=>'wc-g-067','title'=>'Panama vs England','description'=>'Group L — Full-time: Panama 0-2 England','location'=>'New York New Jersey Stadium, New Jersey, US','starts'=>'2026-06-27 21:00:00','ends'=>'2026-06-27 23:00:00'],
    ['uid'=>'wc-g-068','title'=>'Croatia vs Ghana','description'=>'Group L — Full-time: Croatia 2-1 Ghana','location'=>'Philadelphia Stadium, Philadelphia, US','starts'=>'2026-06-27 21:00:00','ends'=>'2026-06-27 23:00:00'],
    ['uid'=>'wc-g-069','title'=>'Colombia vs Portugal','description'=>'Group K — Full-time: Colombia 0-0 Portugal','location'=>'Miami Stadium, Miami, US','starts'=>'2026-06-27 23:30:00','ends'=>'2026-06-28 01:30:00'],
    ['uid'=>'wc-g-070','title'=>'DRC vs Uzbekistan','description'=>'Group K — Full-time: DRC 3-1 Uzbekistan','location'=>'Atlanta Stadium, Atlanta, US','starts'=>'2026-06-27 23:30:00','ends'=>'2026-06-28 01:30:00'],
    ['uid'=>'wc-g-071','title'=>'Algeria vs Austria','description'=>'Group J — Full-time: Algeria 3-3 Austria','location'=>'Kansas City Stadium, Kansas City, US','starts'=>'2026-06-28 02:00:00','ends'=>'2026-06-28 04:00:00'],
    ['uid'=>'wc-g-072','title'=>'Jordan vs Argentina','description'=>'Group J — Full-time: Jordan 1-3 Argentina','location'=>'Dallas Stadium, Dallas, US','starts'=>'2026-06-28 02:00:00','ends'=>'2026-06-28 04:00:00'],
    // ---- Round of 32 (bracket set after group stage) ----
    ['uid'=>'wc-ko-01','title'=>'South Africa vs Canada','description'=>'Round of 32 — Full-time: South Africa 0-1 Canada','location'=>'Los Angeles Stadium, Los Angeles, US','starts'=>'2026-06-28 19:00:00','ends'=>'2026-06-28 21:00:00'],
    ['uid'=>'wc-ko-02','title'=>'Brazil vs Japan','description'=>'Round of 32 — Full-time: Brazil 2-1 Japan','location'=>'Houston Stadium, Houston, US','starts'=>'2026-06-29 19:00:00','ends'=>'2026-06-29 21:00:00'],
    ['uid'=>'wc-ko-03','title'=>'Germany vs Paraguay','description'=>'Round of 32 — Full-time: Germany 1-1 Paraguay (Paraguay win 4-3 on penalties)','location'=>'Boston Stadium, Boston, US','starts'=>'2026-06-29 20:30:00','ends'=>'2026-06-29 22:30:00'],
    ['uid'=>'wc-ko-04','title'=>'Netherlands vs Morocco','description'=>'Round of 32 — Full-time: Netherlands 1-1 Morocco (Morocco win 3-2 on penalties)','location'=>'Estadio Monterrey, Guadalupe, Mexico','starts'=>'2026-06-30 01:00:00','ends'=>'2026-06-30 03:00:00'],
    ['uid'=>'wc-ko-05','title'=>'Ivory Coast vs Norway','description'=>'Round of 32 — Full-time: Ivory Coast 1-2 Norway','location'=>'Dallas Stadium, Dallas, US','starts'=>'2026-06-30 17:00:00','ends'=>'2026-06-30 19:00:00'],
    ['uid'=>'wc-ko-06','title'=>'France vs Sweden','description'=>'Round of 32 — Full-time: France 3-0 Sweden','location'=>'New York New Jersey Stadium, New Jersey, US','starts'=>'2026-06-30 21:00:00','ends'=>'2026-06-30 23:00:00'],
    ['uid'=>'wc-ko-07','title'=>'Mexico vs Ecuador','description'=>'Round of 32 — Full-time: Mexico 2-0 Ecuador','location'=>'Mexico City Stadium, Mexico City, Mexico','starts'=>'2026-07-01 01:00:00','ends'=>'2026-07-01 03:00:00'],
    ['uid'=>'wc-ko-08','title'=>'England vs DR Congo','description'=>'Round of 32 — Full-time: England 2-1 DR Congo','location'=>'Atlanta Stadium, Atlanta, US','starts'=>'2026-07-01 16:00:00','ends'=>'2026-07-01 18:00:00'],
    ['uid'=>'wc-ko-09','title'=>'Belgium vs Senegal','description'=>'Round of 32','location'=>'Seattle Stadium, Seattle, US','starts'=>'2026-07-01 20:00:00','ends'=>'2026-07-01 22:00:00'],
    ['uid'=>'wc-ko-10','title'=>'USA vs Bosnia','description'=>'Round of 32','location'=>'San Francisco Bay Area Stadium, San Francisco, US','starts'=>'2026-07-01 20:00:00','ends'=>'2026-07-01 22:00:00'],
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
    ['uid'=>'wc-ko-27','title'=>'Quarterfinal — Third','location'=>'Miami Stadium, Miami, US','starts'=>'2026-07-11 20:00:00','ends'=>'2026-07-11 22:00:00'],
    ['uid'=>'wc-ko-28','title'=>'Quarterfinal — Fourth','location'=>'Kansas City Stadium, Kansas City, US','starts'=>'2026-07-12 01:00:00','ends'=>'2026-07-12 03:00:00'],
    ['uid'=>'wc-ko-29','title'=>'Semifinal — First','location'=>'Dallas Stadium, Dallas, US','starts'=>'2026-07-14 19:00:00','ends'=>'2026-07-14 21:00:00'],
    ['uid'=>'wc-ko-30','title'=>'Semifinal — Second','location'=>'Atlanta Stadium, Atlanta, US','starts'=>'2026-07-15 19:00:00','ends'=>'2026-07-15 21:00:00'],
    ['uid'=>'wc-ko-31','title'=>'Bronze Medal Match','location'=>'Miami Stadium, Miami, US','starts'=>'2026-07-18 21:00:00','ends'=>'2026-07-18 23:00:00'],
    ['uid'=>'wc-ko-32','title'=>'Final','location'=>'New York New Jersey Stadium, New Jersey, US','starts'=>'2026-07-19 19:00:00','ends'=>'2026-07-19 21:00:00'],
];
foreach ($wc_matches as $e) { upsert_event($pdo, $wc, $e); }

header('Location: /?seeded=1');
exit;
