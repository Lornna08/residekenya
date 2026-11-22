<?php
/**
 * Bootstrap — include this at the top of every entry point.
 * Loads config + core libraries and starts the session.
 */

$configFile = __DIR__ . '/config.php';
if (!file_exists($configFile)) {
    exit('Setup incomplete: copy includes/config.sample.php to includes/config.php and add your database credentials.');
}
require $configFile;
require __DIR__ . '/db.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/Property.php';
require __DIR__ . '/seo.php';
require __DIR__ . '/push.php';
require __DIR__ . '/crm.php';
require __DIR__ . '/content.php';

// Sessions start only when a page needs one (sign-in, forms, flash messages), or when
// the visitor already has a session cookie. Public pages without one stay cookie-free,
// which lets Vercel cache them at the edge.
if (!empty($_COOKIE[SESSION_NAME]) || str_starts_with((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/admin/')) boot_session();

// One-off upgrades run once, on the first request after a deploy.
if ((int) setting('schema_version', '1') < 4) crm_schema();
