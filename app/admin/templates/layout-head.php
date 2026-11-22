<?php
/**
 * Admin layout header for the website-management pages (homepage editor,
 * inquiries, settings…). Same white look, top bar and menu as the workspace app.
 * Expects: $pageTitle (string), $activeAdmin (string key). Optional: $pageLead.
 */
require_once __DIR__ . '/icons.php';
$u = current_user();
// Opening an editor starts the preview from what is published.
if (!empty($previewUrl) && ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') unset($_SESSION['site_draft']);
$pageTitle = $pageTitle ?? 'Admin';
$activeAdmin = $activeAdmin ?? '';

$unreadInquiries = (int) (Database::row("SELECT COUNT(*) n FROM inquiries WHERE is_read = 0")['n'] ?? 0);
$pendingBookings = (int) (Database::row("SELECT COUNT(*) n FROM bookings WHERE status = 'pending'")['n'] ?? 0);
$newSubmissions  = (int) (Database::row("SELECT COUNT(*) n FROM submissions WHERE status = 'new'")['n'] ?? 0);
$__me = Database::row("SELECT name, avatar FROM users WHERE id = ?", [(int) $u['id']]) ?: ['name' => $u['name'], 'avatar' => null];
$__initials = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim((string) $__me['name'])) ?: ['A'], 0, 2))));

$__site = [
    ['inquiries', 'inquiries', 'Website inquiries', 'mail', $unreadInquiries],
    ['bookings', 'bookings', 'Airbnb bookings', 'cal', $pendingBookings],
    ['submissions', 'submissions', 'Property submissions', 'upload', $newSubmissions],
    ['homepage', 'homepage', 'Homepage editor', 'home', 0],
    ['site-editor', 'site-editor', 'Pages & policies', 'file', 0],
    ['site-details', 'site-editor?p=details', 'Contact & socials', 'phone', 0],
    ['quick-listing', 'quick-listing', 'Quick listing', 'plus', 0],
    ['settings', 'settings', 'Website settings', 'gear', 0],
    ['locations', 'locations', 'Locations', 'pin', 0],
    ['categories', 'categories', 'Property types', 'building', 0],
    ['amenities', 'amenities', 'Amenities', 'check', 0],
    ['testimonials', 'testimonials', 'Testimonials', 'star', 0],
    ['agents', 'agents', 'Website agents', 'users', 0],
    ['subscribers', 'subscribers', 'Subscribers', 'mail', 0],
    ['import', 'import-export', 'Import & export', 'download', 0],
    ['users', 'users', 'Users & access', 'user', 0],
    ['activity', 'activity', 'Activity log', 'clock', 0],
];
if ($activeAdmin === 'site-editor' && (($_GET['p'] ?? '') === 'details')) $activeAdmin = 'site-details';
$__nav = [
    'Workspace' => [['overview', 'Overview', 'home'], ['listings', 'Properties', 'building'], ['rentals', 'Rentals', 'key'], ['sales', 'Sales', 'tag'],
        ['deals', 'Deals', 'board'], ['leads', 'Leads', 'user'], ['contacts', 'Contacts', 'users'], ['tasks', 'Tasks & follow-ups', 'task'], ['calendar', 'Calendar', 'cal'], ['chat', 'Team chat', 'chat']],
    'Team & reports' => [['team', 'Team', 'users'], ['commissions', 'Commission reports', 'wallet'], ['analytics', 'Analytics', 'chart'], ['documents', 'Documents', 'file'], ['settings', 'Settings', 'gear']],
];
$__purposes = [['all', 'All properties'], ['sale', 'For sale'], ['rent', 'For rent'], ['airbnb', 'Airbnb / short stay'], ['commercial', 'Commercial'], ['land', 'Land']];
$__propTabs = in_array($activeAdmin, ['properties', 'property-edit'], true);
$__siteBadge = $unreadInquiries + $pendingBookings + $newSubmissions;
$__b = fn(int $n) => $n ? '<i class="side__b">' . ($n > 99 ? '99+' : $n) . '</i>' : '';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#EEF2F8">
<script>try{var d=document.documentElement,t=localStorage.getItem("reside_admin_theme")||"light";d.dataset.theme=t;if(localStorage.getItem("reside_side_min")==="1")d.classList.add("is-side-min");var n=JSON.parse(localStorage.getItem("reside_nav")||"{}");if(n.props)d.classList.add("g-props-off");if(n.site)d.classList.add("g-site-off")}catch(e){}</script>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Reside Admin">
<meta name="format-detection" content="telephone=no">
<link rel="manifest" href="/admin/manifest.webmanifest">
<link rel="icon" href="/admin/icons/admin-32.png" sizes="32x32" type="image/png">
<link rel="apple-touch-icon" href="/admin/icons/admin-180.png">
<title><?= e($pageTitle) ?> · Reside Kenya</title>
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/admin/app/css/app.css?v=<?= ASSET_V ?>">
<link rel="stylesheet" href="/admin/assets/admin.css?v=<?= ASSET_V ?>">
</head>
<body class="admin">
<script>window.RESIDE_ADMIN = <?= json_encode(['csrf' => csrf_token(), 'vapid' => vapid_public_key()]) ?>;</script>
<div class="app lx-app is-chat-off">
  <aside class="side" id="side" aria-label="Main menu">
    <div class="side__h"><a class="brand" href="/admin/#/overview" aria-label="Reside Kenya overview"><span class="brand__m"><img src="/assets/img/favicon-64.png" alt=""></span><span class="brand__w">Reside<em>Kenya</em></span></a><button class="side__min" type="button" data-lx="sidemin" aria-label="Collapse menu" title="Collapse menu"><?= a_icon('left', 16) ?></button><button class="side__x" type="button" data-lx="close" aria-label="Close menu"><?= a_icon('x', 18) ?></button></div>
    <nav class="side__nav">
      <?php foreach ($__nav as $__g => $__items): ?>
        <div class="side__g"><?= e($__g) ?></div>
        <?php foreach ($__items as $t): ?>
          <?php if ($t[0] === 'listings'): ?>
            <div class="side__grp" data-g="props"><div class="side__row"><a href="/admin/#/listings" class="side__a<?= $__propTabs ? ' is-on' : '' ?>" title="Properties"><?= a_icon('building', 18) ?><span>Properties</span></a><button class="side__car" type="button" data-lx="grp" data-g="props" aria-label="Show or hide property types"><?= a_icon('down', 15) ?></button></div>
              <div class="side__sub"><?php foreach ($__purposes as $pp): ?><a href="/admin/#/listings/<?= $pp[0] ?>"><?= e($pp[1]) ?></a><?php endforeach; ?><a href="/admin/#/listings/new" class="side__new"><?= a_icon('plus', 13) ?>New listing</a></div></div>
          <?php else: ?>
            <a href="/admin/#/<?= $t[0] ?>" class="side__a" title="<?= e($t[1]) ?>"><?= a_icon($t[2], 18) ?><span><?= e($t[1]) ?></span></a>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endforeach; ?>
      <button class="side__g side__g--btn" type="button" data-lx="grp" data-g="site">Website<?= $__b($__siteBadge) ?><?= a_icon('down', 14) ?></button>
      <div class="side__gb" data-gb="site">
        <?php foreach ($__site as $s2): if ($s2[0] === 'users' && !user_can('super_admin', 'admin')) continue; ?><a href="/admin/<?= $s2[1] ?>" class="side__a<?= $s2[0] === $activeAdmin ? ' is-on' : '' ?>" title="<?= e($s2[2]) ?>"<?= $s2[0] === $activeAdmin ? ' aria-current="page"' : '' ?>><?= a_icon($s2[3], 18) ?><span><?= e($s2[2]) ?></span><?= $__b($s2[4]) ?></a><?php endforeach; ?>
      </div>
    </nav>
    <div class="side__f">
      <div class="side__thm" role="group" aria-label="Theme"><button type="button" data-lx="theme" data-v="light" title="Light"><?= a_icon('sun', 15) ?><span>Light</span></button><button type="button" data-lx="theme" data-v="dark" title="Dark"><?= a_icon('moon', 15) ?><span>Dark</span></button></div>
      <a class="side__a" href="/" target="_blank" rel="noopener" title="View website"><?= a_icon('globe', 18) ?><span>View website</span></a>
      <a class="side__a" href="/admin/logout" title="Sign out"><?= a_icon('out', 18) ?><span>Sign out</span></a>
    </div>
  </aside>
  <div class="side-scrim" data-lx="close"></div>
  <div class="main lx">
  <header class="top">
    <button class="ib top__menu" type="button" data-lx="drawer" aria-label="Open menu"><?= a_icon('menu', 20) ?></button>
    <a class="brand top__brand" href="/admin/#/overview" aria-label="Reside Kenya overview"><span class="brand__m"><img src="/assets/img/favicon-64.png" alt=""></span><span class="brand__w">Reside<em>Kenya</em></span></a>
    <span class="top__title">Website</span><span class="top__sp"></span>
    <a class="ib ib--round" href="/admin/#/overview?open=search" aria-label="Search"><?= a_icon('search', 18) ?></a>
    <a class="ib ib--round" href="/admin/#/overview?open=notes" aria-label="Notifications"><?= a_icon('bell', 18) ?><?php if ($__siteBadge): ?><i class="ib__dot"></i><?php endif; ?></a>
    <a class="ib ib--round top__hide-s" href="/" target="_blank" rel="noopener" aria-label="View website" title="View website"><?= a_icon('globe', 18) ?></a>
    <button class="ib ib--round top__hide-s" type="button" data-lx="themeflip" aria-label="Switch light or dark" title="Light / dark"><?= a_icon('moon', 18) ?></button>
    <a class="me" href="/admin/#/settings" aria-label="Your profile"><span class="av" style="--s:36px"><?php if (!empty($__me['avatar'])): ?><img src="<?= e(asset_url($__me['avatar'])) ?>" alt="" width="36" height="36"><?php else: ?><span><?= e($__initials) ?></span><?php endif; ?></span></a>
  </header>
  <main class="admin-body" id="main">
    <?php if (!empty($previewUrl)): ?><div class="ed"><div class="ed__main"><?php endif; ?>
    <header class="ph lx-ph"><h1><?= e($pageTitle) ?></h1><?php if (!empty($pageLead)): ?><span class="muted"><?= e($pageLead) ?></span><?php endif; ?></header>
    <?php if ($__flash = flash('success')): ?><div class="a-flash success"><?= a_icon('check', 16) ?><span><?= e($__flash) ?></span></div><?php endif; ?>
    <?php if ($__flash = flash('error')): ?><div class="a-flash error"><?= a_icon('alert', 16) ?><span><?= e($__flash) ?></span></div><?php endif; ?>
