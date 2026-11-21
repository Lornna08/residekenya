<?php
/**
 * Front controller for Vercel.
 *
 * Vercel runs this single PHP function for every request that is not a
 * static file in /public. It maps the URL to the original page script in
 * /app (same URLs the cPanel site used), then runs it unchanged.
 */

// --- Request environment behind Vercel's proxy ---------------------------
if (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https') {
    $_SERVER['HTTPS'] = 'on';
}
if (!empty($_SERVER['HTTP_X_FORWARDED_HOST'])) {
    $_SERVER['HTTP_HOST'] = $_SERVER['HTTP_X_FORWARDED_HOST'];
}

$__appRoot = realpath(__DIR__ . '/../app');
$__path    = rawurldecode(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$__path    = '/' . ltrim(preg_replace('#/+#', '/', $__path), '/');

// One-time data import endpoint (inert unless MIGRATE_KEY is set).
if ($__path === '/__migrate') {
    require __DIR__ . '/../migrate/run.php';
    exit;
}

// --- Pretty URLs (from the original .htaccess) ---------------------------
$__pretty = [
    '/buy'        => ['listings.php', ['purpose' => 'sale']],
    '/rent'       => ['listings.php', ['purpose' => 'rent']],
    '/airbnb'     => ['listings.php', ['purpose' => 'airbnb']],
    '/commercial' => ['listings.php', ['purpose' => 'commercial']],
    '/land'       => ['listings.php', ['purpose' => 'land']],
    '/sitemap.xml'=> ['sitemap.php', []],
];
$__script = null;
$__trim = rtrim($__path, '/');

if (isset($__pretty[$__trim])) {
    [$__script, $__q] = $__pretty[$__trim];
    $_GET += $__q;
} elseif (preg_match('#^/property/([A-Za-z0-9\-]+)/?$#', $__path, $__m)) {
    $__script = 'property.php';
    $_GET['slug'] = $__m[1];
} elseif ($__path === '/' || $__path === '/index.php') {
    $__script = 'index.php';
} elseif ($__trim === '/admin') {
    $__script = 'admin/index.php';
} else {
    $__candidate = ltrim($__path, '/');
    if (!str_ends_with($__candidate, '.php')) $__candidate = rtrim($__candidate, '/') . '.php';
    $__script = $__candidate;
}

// Only real page scripts are reachable; library folders never are.
$__file = $__script ? realpath($__appRoot . '/' . $__script) : false;
$__blocked = '#^(includes|templates|admin/templates)/#';
if (
    !$__file || !is_file($__file) ||
    !str_starts_with($__file, $__appRoot . '/') ||
    preg_match($__blocked, substr($__file, strlen($__appRoot) + 1))
) {
    http_response_code(404);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><meta charset="utf-8"><title>Page not found</title>'
       . '<div style="font-family:system-ui;text-align:center;padding:4rem 1rem">'
       . '<h1 style="font-weight:500">Page not found</h1>'
       . '<p><a href="/">Back to Reside Ke</a></p></div>';
    exit;
}

$_SERVER['SCRIPT_FILENAME'] = $__file;
$_SERVER['SCRIPT_NAME']     = '/' . substr($__file, strlen($__appRoot) + 1);
$_SERVER['PHP_SELF']        = $_SERVER['SCRIPT_NAME'];
$_REQUEST = array_merge($_GET, $_POST);
chdir(dirname($__file));

unset($__path, $__pretty, $__trim, $__m, $__q, $__candidate, $__blocked, $__script);
require $__file;
