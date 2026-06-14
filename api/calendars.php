<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/calendars.php';

$prefs = user_prefs();
$out   = [];
foreach (visible_calendars() as $c) {
    $p = $prefs[(int) $c['id']] ?? null;
    $out[] = [
        'id'         => (int) $c['id'],
        'slug'       => $c['slug'],
        'name'       => $c['name'],
        'color'      => $p['color_override'] ?? $c['color'],
        'icon'       => $c['icon'],
        'visibility' => $c['visibility'],
        'priority'   => $p['priority_override'] !== null && $p !== null
                        ? (int) $p['priority_override'] : (int) $c['default_priority'],
        'enabled'    => $p ? ((int) $p['enabled'] === 1) : true,
    ];
}

$u = current_user();
json_out([
    'calendars' => $out,
    'user'      => $u ? ['email' => $u['email'], 'is_admin' => is_admin($u)] : null,
]);
