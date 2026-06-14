<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';

$c     = config();
$code  = (string) ($_GET['code'] ?? '');
$state = (string) ($_GET['state'] ?? '');

// Verify CSRF state: timestamp.nonce.HMAC, max age 10 min.
$sp = explode('.', $state);
if (count($sp) !== 3) {
    http_response_code(400);
    exit('Bad OAuth state');
}
[$ts, $nonce, $sig] = $sp;
if (!hash_equals(auth_sign($ts . '.' . $nonce), $sig)) {
    http_response_code(400);
    exit('Invalid OAuth state');
}
if ((int) $ts < time() - 600) {
    http_response_code(400);
    exit('OAuth state expired, please try again');
}
if ($code === '') {
    http_response_code(400);
    exit('Missing authorization code');
}

// Exchange authorization code for tokens.
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'code'          => $code,
        'client_id'     => $c['google_client_id'],
        'client_secret' => $c['google_client_secret'],
        'redirect_uri'  => $c['oauth_redirect_uri'],
        'grant_type'    => 'authorization_code',
    ]),
]);
$resp = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$tok = json_decode((string) $resp, true);
if ($http !== 200 || empty($tok['access_token'])) {
    http_response_code(502);
    exit('Token exchange failed');
}

// Fetch the user's profile.
$ch = curl_init('https://openidconnect.googleapis.com/v1/userinfo');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $tok['access_token']],
]);
$uresp = curl_exec($ch);
$uhttp = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$info = json_decode((string) $uresp, true);
if ($uhttp !== 200 || empty($info['sub'])) {
    http_response_code(502);
    exit('Could not fetch Google profile');
}

// Upsert the user, then issue the auth cookie.
$pdo  = db();
$stmt = $pdo->prepare(
    'INSERT INTO users (google_sub, email, display_name) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE email = VALUES(email), display_name = VALUES(display_name)'
);
$stmt->execute([$info['sub'], $info['email'] ?? '', $info['name'] ?? null]);

$stmt = $pdo->prepare('SELECT * FROM users WHERE google_sub = ?');
$stmt->execute([$info['sub']]);
$user = $stmt->fetch();

issue_auth_cookie($user);
header('Location: /');
exit;
