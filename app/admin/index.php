<?php
/**
 * admin/index.php — the Reside Kenya workspace.
 * Serves the single-page app (overview, properties, deals, leads, contacts,
 * tasks, calendar, team, commissions, analytics, documents, chat) with the
 * whole live state embedded, so the first screen paints without a round trip.
 */
require __DIR__ . '/../includes/bootstrap.php';
require_login();
crm_schema();

$u = current_user();
$uid = (int) $u['id'];
Database::run("UPDATE users SET last_seen_at = NOW() WHERE id = ?", [$uid]);
$boot = [
    'state' => crm_state($uid),
    'csrf'  => csrf_token(),
    'vapid' => vapid_public_key(),
    'site'  => ['name' => 'Reside Kenya', 'url' => rtrim(BASE_URL, '/'), 'phone' => '+254117959105', 'email' => 'residekenya@gmail.com'],
];
$v = ASSET_V;
header('Cache-Control: no-store');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#EEF2F8">
<script>try{var d=document.documentElement,t=localStorage.getItem("reside_admin_theme")||"light";d.dataset.theme=t;if(localStorage.getItem("reside_side_min")==="1")d.classList.add("is-side-min");var n=JSON.parse(localStorage.getItem("reside_nav")||"{}");if(n.props)d.classList.add("g-props-off");if(n.site)d.classList.add("g-site-off");if(window.innerWidth>1180&&localStorage.getItem("reside_chat_open")==="0")d.classList.add("is-chat-off")}catch(e){}</script>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Reside Admin">
<meta name="format-detection" content="telephone=no">
<meta name="robots" content="noindex, nofollow">
<title>Overview · Reside Kenya</title>
<link rel="manifest" href="/admin/manifest.webmanifest">
<link rel="icon" href="/admin/icons/admin-32.png" sizes="32x32" type="image/png">
<link rel="icon" href="/admin/icons/admin-192.png" sizes="192x192" type="image/png">
<link rel="apple-touch-icon" href="/admin/icons/admin-180.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/admin/app/css/app.css?v=<?= $v ?>">
</head>
<body>
<div class="boot" id="boot"><img src="/assets/img/favicon-64.png" alt="" width="44" height="44"><span>Loading your workspace…</span></div>
<noscript><p style="padding:24px">The Reside Kenya admin needs JavaScript turned on.</p></noscript>
<script>window.RESIDE_BOOT = <?= json_encode($boot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_INVALID_UTF8_SUBSTITUTE) ?>;</script>
<?php foreach (['data', 'store', 'ui', 'app', 'pages-overview', 'pages-listings', 'pages-deals', 'pages-crm', 'pages-ops', 'live'] as $js): ?>
<script src="/admin/app/js/<?= $js ?>.js?v=<?= $v ?>"></script>
<script src="/admin/app/js/table-cards.js?v=<?= $v ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
