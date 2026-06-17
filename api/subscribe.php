<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/calendars.php';
require_once __DIR__ . '/../lib/ical.php';

/**
 * Subscribe to an external .ics feed (Phase 6). Creates a private, read-only
 * "mirror" calendar and does the initial sync.
 *   POST /api/subscribe.php  { feed_url, name?, color?, icon? }
 */

$u = current_user();
if (!$u) { json_out(['error' => 'unauthorized'], 401); }
require_same_origin();
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_out(['error' => 'method_not_allowed'], 405);
}
if (!calendars_has_feed()) {
    json_out(['error' => 'migration_required', 'message' => 'Run db_migrate_phase6.sql to enable subscriptions.'], 503);
}

$body = read_json_body();
$url  = trim((string) ($body['feed_url'] ?? ''));
$name = trim((string) ($body['name'] ?? ''));
if ($url === '') {
    json_out(['error' => 'bad_request', 'message' => 'A feed URL is required.'], 400);
}

$res = ical_fetch_url($url);
if (empty($res['ok'])) {
    json_out(['error' => 'fetch_failed', 'message' => $res['error'] ?? 'Could not fetch that feed.'], 400);
}
$bodyText = (string) ($res['body'] ?? '');
$events   = ical_parse($bodyText);
if (!$events) {
    json_out(['error' => 'bad_request', 'message' => 'No events found at that URL (is it an .ics feed?).'], 400);
}

if ($name === '') {
    if (preg_match('/^X-WR-CALNAME:(.+)$/mi', $bodyText, $m)) { $name = trim($m[1]); }
    if ($name === '') { $name = (string) (parse_url($url, PHP_URL_HOST) ?: 'Subscribed calendar'); }
}
$color = preg_match('/^#[0-9a-fA-F]{6}$/', (string) ($body['color'] ?? '')) ? (string) $body['color'] : '#5b9dd9';
$icon  = trim((string) ($body['icon'] ?? ''));
$icon  = $icon === '' ? null : mb_substr($icon, 0, 16);

$pdo = db();
$c   = create_private_calendar((int) $u['uid'], $name, $color, $icon, $url);
$n   = ical_store_events($pdo, $c['id'], $events, true);
$pdo->prepare('UPDATE calendars SET feed_last_synced = UTC_TIMESTAMP(), feed_etag = ? WHERE id = ?')
    ->execute([$res['etag'] ?? null, $c['id']]);

json_out(['imported' => $n, 'calendar_id' => $c['id'], 'slug' => $c['slug'], 'name' => $name], 201);
