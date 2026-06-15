<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

/**
 * Calendar IDs shared with the current user (via calendar_shares), keyed by id.
 * Defensive: the calendar_shares table is added by db_migrate_phase4.sql, which is
 * applied manually — until then this returns an empty map instead of 500ing.
 *
 * @return array<int,string> calendar_id => role ('viewer'|'editor')
 */
function shared_calendar_roles(): array {
    $u = current_user();
    if (!$u) {
        return [];
    }
    try {
        $stmt = db()->prepare(
            'SELECT calendar_id, role FROM calendar_shares
             WHERE shared_with_user_id = ? OR LOWER(shared_with_email) = ?'
        );
        $stmt->execute([(int) $u['uid'], strtolower((string) $u['email'])]);
    } catch (PDOException $e) {
        return [];
    }
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $id = (int) $r['calendar_id'];
        // editor beats viewer if both somehow present
        if (!isset($out[$id]) || $r['role'] === 'editor') {
            $out[$id] = (string) $r['role'];
        }
    }
    return $out;
}

/**
 * Calendars visible to the current request: all public ones, plus the current
 * user's private ones and any calendars shared with them when logged in.
 */
function visible_calendars(): array {
    $u   = current_user();
    $pdo = db();
    if (!$u) {
        return $pdo->query(
            'SELECT * FROM calendars WHERE visibility = "public"
             ORDER BY default_priority ASC, name ASC'
        )->fetchAll();
    }
    $sharedIds = array_keys(shared_calendar_roles());
    if ($sharedIds) {
        $ph = implode(',', array_fill(0, count($sharedIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT * FROM calendars
             WHERE visibility = 'public' OR owner_user_id = ? OR id IN ($ph)
             ORDER BY default_priority ASC, name ASC"
        );
        $stmt->execute(array_merge([(int) $u['uid']], $sharedIds));
    } else {
        $stmt = $pdo->prepare(
            "SELECT * FROM calendars
             WHERE visibility = 'public' OR owner_user_id = ?
             ORDER BY default_priority ASC, name ASC"
        );
        $stmt->execute([(int) $u['uid']]);
    }
    return $stmt->fetchAll();
}

/** Per-user preference rows keyed by calendar_id (empty for guests). */
function user_prefs(): array {
    $u = current_user();
    if (!$u) {
        return [];
    }
    $stmt = db()->prepare('SELECT * FROM user_calendar_prefs WHERE user_id = ?');
    $stmt->execute([(int) $u['uid']]);
    $out = [];
    foreach ($stmt->fetchAll() as $r) {
        $out[(int) $r['calendar_id']] = $r;
    }
    return $out;
}

/**
 * Calendar IDs the current user may add/edit/delete events on:
 * owned calendars, calendars shared as 'editor', and (for admins) public ones.
 *
 * @return array<int,bool> set of calendar_id => true
 */
function editable_calendar_ids(): array {
    $u = current_user();
    if (!$u) {
        return [];
    }
    $pdo = db();
    $ids = [];
    $stmt = $pdo->prepare('SELECT id FROM calendars WHERE owner_user_id = ?');
    $stmt->execute([(int) $u['uid']]);
    foreach ($stmt->fetchAll() as $r) {
        $ids[(int) $r['id']] = true;
    }
    if (is_admin($u)) {
        foreach ($pdo->query('SELECT id FROM calendars WHERE visibility = "public"')->fetchAll() as $r) {
            $ids[(int) $r['id']] = true;
        }
    }
    foreach (shared_calendar_roles() as $id => $role) {
        if ($role === 'editor') {
            $ids[$id] = true;
        }
    }
    return $ids;
}

/** True if the current user may write events to the given calendar. */
function can_edit_calendar(int $calId): bool {
    $ids = editable_calendar_ids();
    return isset($ids[$calId]);
}

/**
 * True if the current user may manage the calendar itself (rename, recolor,
 * delete, share): the owner, or an admin for public calendars.
 */
function can_manage_calendar(int $calId): bool {
    $u = current_user();
    if (!$u) {
        return false;
    }
    $stmt = db()->prepare('SELECT owner_user_id, visibility FROM calendars WHERE id = ?');
    $stmt->execute([$calId]);
    $row = $stmt->fetch();
    if (!$row) {
        return false;
    }
    if ((int) $row['owner_user_id'] === (int) $u['uid']) {
        return true;
    }
    return $row['visibility'] === 'public' && is_admin($u);
}

/** URL-safe slug from a name, with a short random suffix for uniqueness. */
function make_slug(string $name): string {
    $base = strtolower(trim($name));
    $base = preg_replace('/[^a-z0-9]+/', '-', $base) ?? '';
    $base = trim($base, '-');
    if ($base === '') {
        $base = 'cal';
    }
    return substr($base, 0, 100) . '-' . substr(bin2hex(random_bytes(3)), 0, 5);
}

/** Stable-ish unique id for an event row. */
function make_event_uid(): string {
    return 'nc-' . bin2hex(random_bytes(8)) . '@cal.stamih.com';
}

/** Read and decode a JSON request body (returns [] on empty/invalid). */
function read_json_body(): array {
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : [];
}

/**
 * Lightweight CSRF guard for state-changing endpoints. The auth cookie is
 * SameSite=Lax, and we additionally require a custom header that browsers will
 * not attach to cross-site form posts. app.js sends X-Requested-With on writes.
 */
function require_same_origin(): void {
    if (empty($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        json_out(['error' => 'forbidden', 'message' => 'Missing X-Requested-With'], 403);
    }
}

/** Parse an incoming datetime (ISO for timed, Y-m-d for all-day) to a UTC SQL string. */
function parse_to_utc(string $value, bool $allDay): string {
    if ($allDay) {
        $d = DateTime::createFromFormat('!Y-m-d', substr($value, 0, 10), new DateTimeZone('UTC'));
        if (!$d) {
            throw new InvalidArgumentException('Invalid all-day date: ' . $value);
        }
        return $d->format('Y-m-d 00:00:00');
    }
    $d = new DateTime($value);
    $d->setTimezone(new DateTimeZone('UTC'));
    return $d->format('Y-m-d H:i:s');
}
