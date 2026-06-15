<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/calendars.php';

/**
 * Calendar sharing API (Phase 4).
 *   GET    /api/shares.php?calendar_id=ID   list shares (manager only)
 *   POST   /api/shares.php                  add/update a share {calendar_id, email, role}
 *   DELETE /api/shares.php                  remove a share {id} or {calendar_id, email}
 *
 * Requires the calendar_shares table (db_migrate_phase4.sql). If it is missing,
 * endpoints return a clear 503 telling the admin to run the migration.
 */

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$u = current_user();
if (!$u) {
    json_out(['error' => 'unauthorized'], 401);
}

$pdo = db();

function shares_table_ready(PDO $pdo): bool {
    try {
        $pdo->query('SELECT 1 FROM calendar_shares LIMIT 1');
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

if (!shares_table_ready($pdo)) {
    json_out(['error' => 'migration_required', 'message' => 'Run db_migrate_phase4.sql to enable sharing.'], 503);
}

if ($method === 'GET') {
    $calId = (int) ($_GET['calendar_id'] ?? 0);
    if ($calId <= 0 || !can_manage_calendar($calId)) {
        json_out(['error' => 'forbidden'], 403);
    }
    $stmt = $pdo->prepare(
        'SELECT id, shared_with_email AS email, role, (shared_with_user_id IS NOT NULL) AS accepted
         FROM calendar_shares WHERE calendar_id = ? ORDER BY shared_with_email ASC'
    );
    $stmt->execute([$calId]);
    $shares = array_map(function ($r) {
        return ['id' => (int) $r['id'], 'email' => $r['email'], 'role' => $r['role'], 'accepted' => (bool) $r['accepted']];
    }, $stmt->fetchAll());
    json_out(['shares' => $shares]);
}

require_same_origin();
$body = read_json_body();

if ($method === 'POST') {
    $calId = (int) ($body['calendar_id'] ?? 0);
    $email = strtolower(trim((string) ($body['email'] ?? '')));
    $role  = (string) ($body['role'] ?? 'viewer');
    if (!in_array($role, ['viewer', 'editor'], true)) {
        $role = 'viewer';
    }
    if ($calId <= 0 || !can_manage_calendar($calId)) {
        json_out(['error' => 'forbidden'], 403);
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_out(['error' => 'bad_request', 'message' => 'A valid email is required.'], 400);
    }
    if (strtolower((string) $u['email']) === $email) {
        json_out(['error' => 'bad_request', 'message' => "You already have access to your own calendar."], 400);
    }
    // Resolve to an existing user if they have logged in before.
    $uid = null;
    $q = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = ? LIMIT 1');
    $q->execute([$email]);
    $found = $q->fetchColumn();
    if ($found) { $uid = (int) $found; }

    $stmt = $pdo->prepare(
        'INSERT INTO calendar_shares (calendar_id, shared_with_email, shared_with_user_id, role, created_by)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE role = VALUES(role), shared_with_user_id = VALUES(shared_with_user_id)'
    );
    $stmt->execute([$calId, $email, $uid, $role, (int) $u['uid']]);
    json_out(['ok' => true, 'share' => ['email' => $email, 'role' => $role, 'accepted' => $uid !== null]], 201);
}

if ($method === 'DELETE') {
    $calId = (int) ($body['calendar_id'] ?? 0);
    $shareId = (int) ($body['id'] ?? 0);
    if ($shareId > 0) {
        // Resolve the calendar from the share to authorize.
        $q = $pdo->prepare('SELECT calendar_id FROM calendar_shares WHERE id = ?');
        $q->execute([$shareId]);
        $cid = (int) ($q->fetchColumn() ?: 0);
        if ($cid <= 0 || !can_manage_calendar($cid)) {
            json_out(['error' => 'forbidden'], 403);
        }
        $pdo->prepare('DELETE FROM calendar_shares WHERE id = ?')->execute([$shareId]);
        json_out(['ok' => true]);
    }
    $email = strtolower(trim((string) ($body['email'] ?? '')));
    if ($calId <= 0 || $email === '' || !can_manage_calendar($calId)) {
        json_out(['error' => 'forbidden'], 403);
    }
    $pdo->prepare('DELETE FROM calendar_shares WHERE calendar_id = ? AND LOWER(shared_with_email) = ?')
        ->execute([$calId, $email]);
    json_out(['ok' => true]);
}

json_out(['error' => 'method_not_allowed'], 405);
