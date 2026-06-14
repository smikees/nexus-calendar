<?php
// config.example.php — template for the live config.
// Copy this to config.php ON THE SERVER (cPanel File Manager, in the doc root)
// and fill in real values. NEVER commit config.php; it is blocked from web
// access by .htaccess and ignored by git.

return [
    // --- Database (cPanel -> MySQL Databases) ---
    'db_host' => 'localhost',
    'db_name' => 't2hu9otd1ek3_nexuscal',
    'db_user' => 't2hu9otd1ek3_caluser',      // the DB user you create
    'db_pass' => 'CHANGE_ME',                  // that user's password

    // --- Google OAuth (Google Cloud Console -> Credentials) ---
    'google_client_id'     => 'CHANGE_ME.apps.googleusercontent.com',
    'google_client_secret' => 'CHANGE_ME',
    'oauth_redirect_uri'   => 'https://cal.stamih.com/auth/callback.php',

    // --- App secret: signs HMAC auth cookies + OAuth state. ---
    // Use a long random string (64 hex chars). One is provided in chat.
    'app_secret' => 'CHANGE_ME',

    // --- Admins: these emails may create/manage PUBLIC calendars. ---
    'admin_emails' => ['mihai.stanculescu@gmail.com'],
];
