<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

/**
 * Calendars visible to the current request: all public ones, plus the
 * current user's private ones when logged in.
 */
function visible_calendars(): array {
    $u   = current_user();
    $pdo = db();
    if ($u) {
        $stmt = $pdo->prepare(
            'SELECT * FROM calendars
             WHERE visibility = "public" OR owner_user_id = ?
             ORDER BY default_priority ASC, name ASC'
        );
        $stmt->execute([(int) $u['uid']]);
    } else {
        $stmt = $pdo->query(
            'SELECT * FROM calendars
             WHERE visibility = "public"
             ORDER BY default_priority ASC, name ASC'
        );
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
