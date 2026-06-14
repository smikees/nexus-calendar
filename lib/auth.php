<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';

const AUTH_COOKIE = 'cal_auth';
const AUTH_TTL    = 2592000; // 30 days

function b64url_encode(string $s): string {
    return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
}
function b64url_decode(string $s): string {
    return base64_decode(strtr($s, '-_', '+/')) ?: '';
}
function auth_sign(string $payload): string {
    return hash_hmac('sha256', $payload, (string) config()['app_secret']);
}

function issue_auth_cookie(array $user): void {
    $payload = [
        'sub'   => $user['google_sub'],
        'email' => $user['email'],
        'name'  => $user['display_name'],
        'uid'   => (int) $user['id'],
        'iat'   => time(),
        'exp'   => time() + AUTH_TTL,
    ];
    $b   = b64url_encode((string) json_encode($payload));
    $sig = auth_sign($b);
    setcookie(AUTH_COOKIE, $b . '.' . $sig, [
        'expires'  => time() + AUTH_TTL,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function clear_auth_cookie(): void {
    setcookie(AUTH_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function current_user(): ?array {
    if (empty($_COOKIE[AUTH_COOKIE])) {
        return null;
    }
    $parts = explode('.', $_COOKIE[AUTH_COOKIE]);
    if (count($parts) !== 2) {
        return null;
    }
    [$b, $sig] = $parts;
    if (!hash_equals(auth_sign($b), $sig)) {
        return null;
    }
    $data = json_decode(b64url_decode($b), true);
    if (!is_array($data) || (int) ($data['exp'] ?? 0) < time()) {
        return null;
    }
    return $data;
}

function is_admin(?array $user = null): bool {
    $user = $user ?? current_user();
    if (!$user) {
        return false;
    }
    $admins = array_map('strtolower', (array) config()['admin_emails']);
    return in_array(strtolower((string) $user['email']), $admins, true);
}
