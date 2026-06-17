<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/calendars.php';
require_once __DIR__ . '/../lib/ical.php';

/**
 * Manage a calendar's public subscription token (Phase 6).
 *   POST /api/feed_token.php  { calendar_id, action: 'enable'|'regenerate'|'disable' }
 * Owner / public-admin only. Returns the token (frontend builds the URL from it).
 */

$u = current_user();
if (!$u) { json_out(['error' => 'unauthorized'], 401); }
require_same_origin();
if (strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    json_out(['error' => 'method_not_allowed'], 405);
}

$body   = read_json_body();
$calId  = (int) ($body['calendar_id'] ?? 0);
$action = (string) ($body['action'] ?? 'enable');
if ($calId <= 0 || !can_manage_calendar($calId)) {
    json_out(['error' => 'forbidden'], 403);
}

$pdo = db();
if ($action === 'disable') {
    $pdo->prepare('UPDATE calendars SET feed_token = NULL WHERE id = ?')->execute([$calId]);
    json_out(['token' => null]);
}

// enable (only if absent) or regenerate (always new)
$token = null;
if ($action !== 'regenerate') {
    $stmt = $pdo->prepare('SELECT feed_token FROM calendars WHERE id = ?');
    $stmt->execute([$calId]);
    $token = $stmt->fetchColumn() ?: null;
}
if (!$token) {
    $token = ical_gen_feed_token();
    $pdo->prepare('UPDATE calendars SET feed_token = ? WHERE id = ?')->execute([$token, $calId]);
}
json_out(['token' => $token]);
