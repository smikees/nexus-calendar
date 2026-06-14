<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';

$c     = config();
$ts    = time();
$nonce = bin2hex(random_bytes(8));
$state = $ts . '.' . $nonce . '.' . auth_sign($ts . '.' . $nonce);

$params = http_build_query([
    'client_id'     => $c['google_client_id'],
    'redirect_uri'  => $c['oauth_redirect_uri'],
    'response_type' => 'code',
    'scope'         => 'openid email profile',
    'state'         => $state,
    'access_type'   => 'online',
    'prompt'        => 'select_account',
]);

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . $params);
exit;
