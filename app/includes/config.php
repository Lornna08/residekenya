<?php
/**
 * Reside Kenya — Configuration (Vercel edition)
 * All secrets come from environment variables set in the Vercel project.
 * Nothing secret is stored in this file.
 */

// ---- Database (Neon Postgres, connected through Vercel) ----
define('DATABASE_URL', getenv('DATABASE_URL') ?: (getenv('POSTGRES_URL') ?: 'postgres://postgres@localhost:5432/reside'));

// ---- Site ----
define('SITE_NAME', 'Reside Kenya');

// Base URL with NO trailing slash. Follows the host the site is served on
// (residekenya.com in production, *.vercel.app for previews).
(function () {
    $env = getenv('SITE_URL');
    if ($env) { define('BASE_URL', rtrim($env, '/')); return; }
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? 'residekenya.com');
    $host = preg_replace('/[^A-Za-z0-9.\-:]/', '', $host);
    if ($host === 'www.residekenya.com') $host = 'residekenya.com';
    $local = preg_match('/^(localhost|127\.0\.0\.1)(:\d+)?$/', $host);
    define('BASE_URL', ($local ? 'http://' : 'https://') . $host);
})();
define('ADMIN_EMAIL', 'residekenya@gmail.com');

// ---- Paths ----
define('ROOT_PATH', dirname(__DIR__));                       // app/ folder
define('PUBLIC_PATH', dirname(ROOT_PATH) . '/public');       // static files
define('UPLOAD_PATH', PUBLIC_PATH . '/uploads');             // only used locally
define('UPLOAD_URL', BASE_URL . '/uploads');

// ---- Uploaded files storage (Vercel Blob) ----
define('BLOB_TOKEN', getenv('BLOB_READ_WRITE_TOKEN') ?: '');

// ---- Security ----
define('SESSION_NAME', 'reside_sess');
define('CSRF_TOKEN_NAME', '_csrf');
define('APP_KEY', getenv('APP_KEY') ?: 'reside-kenya-local-key');

// ---- Uploads ----
define('MAX_UPLOAD_BYTES', 8 * 1024 * 1024); // 8MB per image
define('ALLOWED_IMAGE_EXT', ['jpg','jpeg','png','webp','gif']);
define('ALLOWED_DOC_EXT', ['pdf']);

// ---- reCAPTCHA (optional; leave blank to disable) ----
define('RECAPTCHA_SITE_KEY', getenv('RECAPTCHA_SITE_KEY') ?: '');
define('RECAPTCHA_SECRET_KEY', getenv('RECAPTCHA_SECRET_KEY') ?: '');

// ---- Environment ----
define('APP_ENV', getenv('APP_ENV') ?: 'production');

if (APP_ENV === 'production') {
    error_reporting(0);
    ini_set('display_errors', '0');
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
}
date_default_timezone_set('Africa/Nairobi');
define('ASSET_V', '20261122j');
