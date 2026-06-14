<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/auth.php';

clear_auth_cookie();
header('Location: /');
exit;
