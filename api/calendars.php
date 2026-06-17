<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/calendars.php';

$u        = current_user();
$isAdmin  = $u ? is_admin($u) : false;
$editable = editable_calendar_ids();
$shared   = $u ? shared_calendar_roles() : [];

$prefs = user_prefs();
$staleAfter = 6 * 3600; // feeds older than 6h are considered stale (lazy re-sync)
$out   = [];
foreach (visible_calendars() as $c) {
    $cid   = (int) $c['id'];
    $p     = $prefs[$cid] ?? null;
    $owned = $u && (int) $c['owner_user_id'] === (int) $u['uid'];
    $role  = $owned ? 'owner'
           : (($isAdmin && $c['visibility'] === 'public') ? 'admin'
           : ($shared[$cid] ?? ($c['visibility'] === 'public' ? 'public' : 'viewer')));
    $feedUrl    = $c['feed_url'] ?? null;            // present only after phase-6 migration
    $isFeed     = !empty($feedUrl);
    $lastSynced = $c['feed_last_synced'] ?? null;
    $feedStale  = $isFeed && (empty($lastSynced) || strtotime($lastSynced . ' UTC') < time() - $staleAfter);
    $out[] = [
        'id'         => $cid,
        'slug'       => $c['slug'],
        'name'       => $c['name'],
        'color'      => $p['color_override'] ?? $c['color'],
        'icon'       => $c['icon'],
        'visibility' => $c['visibility'],
        'priority'   => $p['priority_override'] !== null && $p !== null
                        ? (int) $p['priority_override'] : (int) $c['default_priority'],
        'enabled'    => $p ? ((int) $p['enabled'] === 1) : true,
        'owned'      => $owned,
        'role'       => $role,
        // Feed (subscribed) calendars are read-only mirrors: not event-editable.
        'canEdit'    => isset($editable[$cid]) && !$isFeed,
        'canManage'  => $owned || ($isAdmin && $c['visibility'] === 'public'),
        'isFeed'     => $isFeed,
        'feedUrl'    => $isFeed ? $feedUrl : null,
        'feedLastSynced' => $lastSynced,
        'feedStale'  => $feedStale,
    ];
}

$userOut = null;
if ($u) {
    $row = [];
    try {
        $stmt = db()->prepare('SELECT email, display_name, avatar_url FROM users WHERE id = ?');
        $stmt->execute([(int) $u['uid']]);
        $row = $stmt->fetch() ?: [];
    } catch (PDOException $e) {
        // avatar_url not migrated yet; fall back to core columns.
        $stmt = db()->prepare('SELECT email, display_name FROM users WHERE id = ?');
        $stmt->execute([(int) $u['uid']]);
        $row = $stmt->fetch() ?: [];
    }
    $userOut = [
        'email'    => $row['email'] ?? $u['email'],
        'name'     => $row['display_name'] ?? null,
        'avatar'   => $row['avatar_url'] ?? null,
        'is_admin' => is_admin($u),
    ];
}
json_out(['calendars' => $out, 'user' => $userOut]);
