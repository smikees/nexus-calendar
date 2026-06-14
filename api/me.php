<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';

$u = current_user();
json_out([
    'authenticated' => $u !== null,
    'user' => $u ? [
        'email'    => $u['email'],
        'name'     => $u['name'],
        'is_admin' => is_admin($u),
    ] : null,
]);
