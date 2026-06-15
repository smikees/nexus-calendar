<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/calendars.php';

/**
 * Calendar write API (Phase 4).
 *   POST   /api/calendar.php   create a private calendar (owned by current user)
 *   PATCH  /api/calendar.php   rename / recolor / change icon (owner or admin-public)
 *   DELETE /api/calendar.php   delete a calendar + its events (owner only)
 */

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$u = current_user();
if (!$u) {
    json_out(['error' => 'unauthorized'], 401);
}
require_same_origin();

$body = read_json_body();
$pdo  = db();

function clean_color(?string $c): string {
    $c = (string) $c;
    return preg_match('/^#[0-9a-fA-F]{6}$/', $c) ? $c : '#5b9dd9';
}

if ($method === 'POST') {
    $name = trim((string) ($body['name'] ?? ''));
    if ($name === '') {
        json_out(['error' => 'bad_request', 'message' => 'name is required'], 400);
    }
    $color = clean_color($body['color'] ?? null);
    $icon  = trim((string) ($body['icon'] ?? ''));
    $icon  = $icon === '' ? null : mb_substr($icon, 0, 16);

    // Generate a unique slug (retry on the rare collision).
    $slug = make_slug($name);
    $stmt = $pdo->prepare(
        'INSERT INTO calendars (owner_user_id, slug, name, color, icon, visibility, default_priority, timezone)
         VALUES (?, ?, ?, ?, ?, "private", 100, "UTC")'
    );
    for ($try = 0; $try < 3; $try++) {
        try {
            $stmt->execute([(int) $u['uid'], $slug, mb_substr($name, 0, 200), $color, $icon]);
            break;
        } catch (PDOException $e) {
            if ($try === 2) { throw $e; }
            $slug = make_slug($name);
        }
    }
    $id = (int) $pdo->lastInsertId();
    json_out(['calendar' => [
        'id' => $id, 'slug' => $slug, 'name' => $name, 'color' => $color, 'icon' => $icon,
        'visibility' => 'private', 'owned' => true, 'canEdit' => true, 'canManage' => true,
    ]], 201);
}

if ($method === 'PATCH' || $method === 'PUT') {
    $id = (int) ($body['id'] ?? 0);
    if ($id <= 0 || !can_manage_calendar($id)) {
        json_out(['error' => 'forbidden'], 403);
    }
    $fields = [];
    $args   = [];
    if (array_key_exists('name', $body)) {
        $n = trim((string) $body['name']);
        if ($n === '') { json_out(['error' => 'bad_request', 'message' => 'name cannot be empty'], 400); }
        $fields[] = 'name = ?'; $args[] = mb_substr($n, 0, 200);
    }
    if (array_key_exists('color', $body)) { $fields[] = 'color = ?'; $args[] = clean_color($body['color']); }
    if (array_key_exists('icon', $body))  {
        $icon = trim((string) $body['icon']);
        $fields[] = 'icon = ?'; $args[] = $icon === '' ? null : mb_substr($icon, 0, 16);
    }
    if (!$fields) {
        json_out(['error' => 'bad_request', 'message' => 'nothing to update'], 400);
    }
    $args[] = $id;
    $pdo->prepare('UPDATE calendars SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($args);
    json_out(['ok' => true]);
}

if ($method === 'DELETE') {
    $id = (int) ($body['id'] ?? 0);
    if ($id <= 0) {
        json_out(['error' => 'bad_request', 'message' => 'id is required'], 400);
    }
    // Only the owner may delete (avoids an admin nuking a public calendar by accident).
    $stmt = $pdo->prepare('SELECT owner_user_id FROM calendars WHERE id = ?');
    $stmt->execute([$id]);
    $row = $stmt->fetch();
    if (!$row || (int) $row['owner_user_id'] !== (int) $u['uid']) {
        json_out(['error' => 'forbidden', 'message' => 'Only the owner can delete a calendar.'], 403);
    }
    $pdo->prepare('DELETE FROM calendars WHERE id = ?')->execute([$id]); // events cascade
    json_out(['ok' => true]);
}

json_out(['error' => 'method_not_allowed'], 405);
