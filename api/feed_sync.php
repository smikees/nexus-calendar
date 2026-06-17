<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/calendars.php';
require_once __DIR__ . '/../lib/ical.php';

/**
 * Re-sync a subscribed calendar from its feed (Phase 6).
 *   POST /api/feed_sync.php  { calendar_id }
 * Replaces the calendar's events with the current feed contents. Owner/admin only.
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

$body  = read_json_body();
$calId = (int) ($body['calendar_id'] ?? 0);
if ($calId <= 0) {
    json_out(['error' => 'bad_request', 'message' => 'calendar_id is required'], 400);
}

$pdo  = db();
$stmt = $pdo->prepare('SELECT owner_user_id, feed_url, feed_etag FROM calendars WHERE id = ?');
$stmt->execute([$calId]);
$row = $stmt->fetch();
if (!$row) { json_out(['error' => 'not_found'], 404); }
if ((int) $row['owner_user_id'] !== (int) $u['uid'] && !is_admin($u)) {
    json_out(['error' => 'forbidden'], 403);
}
if (empty($row['feed_url'])) {
    json_out(['error' => 'bad_request', 'message' => 'That calendar is not a feed subscription.'], 400);
}

$res = ical_fetch_url((string) $row['feed_url'], $row['feed_etag'] ?: null);
if (empty($res['ok'])) {
    json_out(['error' => 'fetch_failed', 'message' => $res['error'] ?? 'Could not fetch the feed.'], 400);
}
if (!empty($res['notModified'])) {
    $pdo->prepare('UPDATE calendars SET feed_last_synced = UTC_TIMESTAMP() WHERE id = ?')->execute([$calId]);
    json_out(['notModified' => true, 'imported' => null, 'last_synced' => gmdate('c')]);
}
$events = ical_parse((string) ($res['body'] ?? ''));
$n = ical_store_events($pdo, $calId, $events, true);
$pdo->prepare('UPDATE calendars SET feed_last_synced = UTC_TIMESTAMP(), feed_etag = ? WHERE id = ?')
    ->execute([$res['etag'] ?? null, $calId]);

json_out(['imported' => $n, 'last_synced' => gmdate('c')]);
