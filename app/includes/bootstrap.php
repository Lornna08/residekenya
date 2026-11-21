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

boot_session();
