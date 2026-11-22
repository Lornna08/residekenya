<?php
/**
 * Admin layout header for the website-management pages (homepage editor,
 * inquiries, settings…). Same white look, top bar and menu as the workspace app.
 * Expects: $pageTitle (string), $activeAdmin (string key). Optional: $pageLead.
 */
require_once __DIR__ . '/icons.php';
$u = current_user();
$pageTitle = $pageTitle ?? 'Admin';
$activeAdmin = $activeAdmin ?? '';

$unreadInquiries = (int) (Database::row("SELECT COUNT(*) n FROM inquiries WHERE is_read = 0")['n'] ?? 0);
$pendingBookings = (int) (Database::row("SELECT COUNT(*) n FROM bookings WHERE status = 'pending'")['n'] ?? 0);
$newSubmissions  = (int) (Database::row("SELECT COUNT(*) n FROM submissions WHERE status = 'new'")['n'] ?? 0);
$__me = Database::row("SELECT name, avatar FROM users WHERE id = ?", [(int) $u['id']]) ?: ['name' => $u['name'], 'avatar' => null];
$__initials = strtoupper(implode('', array_map(fn($w) => mb_substr($w, 0, 1), array_slice(preg_split('/\s+/', trim((string) $__me['name'])) ?: ['A'], 0, 2))));

$__site = [
    ['inquiries', 'inquiries.php', 'Website inquiries', 'mail', $unreadInquiries],
    ['bookings', 'bookings.php', 'Airbnb bookings', 'cal', $pendingBookings],
    ['submissions', 'submissions.php', 'Property submissions', 'upload', $newSubmissions],
    ['quick-listing', 'quick-listing.php', 'Quick listing', 'plus', 0],
    ['homepage', 'homepage.php', 'Homepage editor', 'home', 0],
    ['settings', 'settings.php', 'Website settings', 'gear', 0],
    ['locations', 'locations.php', 'Locations', 'pin', 0],
    ['categories', 'categories.php', 'Property types', 'building', 0],
    ['amenities', 'amenities.php', 'Amenities', 'check', 0],
    ['testimonials', 'testimonials.php', 'Testimonials', 'star', 0],
    ['agents', 'agents.php', 'Website agents', 'users', 0],
    ['subscribers', 'subscribers.php', 'Subscribers', 'mail', 0],
    ['import', 'import-export.php', 'Import & export', 'download', 0],
    ['users', 'users.php', 'Users & access', 'user', 0],
    ['activity', 'activity.php', 'Activity log', 'clock', 0],
];
$__app = [
    ['overview', 'Overview', 'home'], ['listings', 'Properties', 'building'], ['rentals', 'Rentals', 'key'], ['sales', 'Sales', 'tag'],
    ['deals', 'Deals', 'board'], ['leads', 'Leads', 'user'], ['contacts', 'Contacts', 'users'],
];
$__more = [
    ['tasks', 'Tasks & follow-ups', 'task'], ['calendar', 'Calendar', 'cal'], ['team', 'Team', 'users'], ['commissions', 'Commission reports', 'wallet'],
    ['analytics', 'Analytics', 'chart'], ['documents', 'Documents', 'file'], ['settings', 'Settings', 'gear'],
];
$__propTabs = in_array($activeAdmin, ['properties', 'property-edit'], true);
$__siteBadge = $unreadInquiries + $pendingBookings + $newSubmissions;
$__cur = null; foreach ($__site as $s) if ($s[0] === $activeAdmin) $__cur = $s;
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#F1F3F8">
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
<div class="main lx">
  <header class="top">
    <button class="ib top__menu" type="button" data-lx="drawer" aria-label="Open navigation"><?= a_icon('menu', 20) ?></button>
    <a class="brand" href="/admin/#/overview" aria-label="Reside Kenya overview"><span class="brand__m"><img src="/assets/img/favicon-64.png" alt=""></span><span class="brand__w">Reside<em>Kenya</em></span></a>
    <span class="top__sp"></span>
    <a class="ib ib--round" href="/admin/#/overview?open=search" aria-label="Search"><?= a_icon('search', 18) ?></a>
    <a class="ib ib--round" href="/admin/#/overview?open=notes" aria-label="Notifications"><?= a_icon('bell', 18) ?><?php if ($__siteBadge): ?><i class="ib__dot"></i><?php endif; ?></a>
    <a class="ib ib--round top__hide-s" href="/" target="_blank" rel="noopener" aria-label="View website" title="View website"><?= a_icon('globe', 18) ?></a>
    <a class="ib ib--round" href="/admin/#/chat" aria-label="Team chat"><?= a_icon('chat', 18) ?></a>
    <a class="me" href="/admin/#/settings" aria-label="Your profile"><span class="av" style="--s:36px"><?php if (!empty($__me['avatar'])): ?><img src="<?= e(asset_url($__me['avatar'])) ?>" alt="" width="36" height="36"><?php else: ?><span><?= e($__initials) ?></span><?php endif; ?></span></a>
  </header>
  <nav class="tabs" aria-label="Primary">
    <?php foreach ($__app as $t): ?><a class="tab<?= $t[0] === 'listings' && $__propTabs ? ' is-on' : '' ?>" href="/admin/#/<?= $t[0] ?>"><?= e($t[1]) ?></a><?php endforeach; ?>
    <button class="tab tab--more<?= $__propTabs ? '' : ' is-on' ?>" type="button" data-lx="more" aria-haspopup="menu"><?= e($__cur ? $__cur[2] : ($__propTabs ? 'More' : $pageTitle)) ?><?php if ($__siteBadge): ?><i class="tab__b tab__b--r"><?= $__siteBadge ?></i><?php endif; ?><?= a_icon('down', 14) ?></button>
  </nav>
  <div class="menu lx-menu" id="lxMore" role="menu" hidden>
    <button disabled><?= a_icon('building', 15) ?><span>Workspace</span></button>
    <?php foreach ($__more as $m): ?><a href="/admin/#/<?= $m[0] ?>"><?= a_icon($m[2], 15) ?><span><?= e($m[1]) ?></span></a><?php endforeach; ?>
    <hr><button disabled><?= a_icon('globe', 15) ?><span>Website</span></button>
    <?php foreach ($__site as $s): if ($s[0] === 'users' && !user_can('super_admin', 'admin')) continue; ?><a href="/admin/<?= $s[1] ?>" class="<?= $s[0] === $activeAdmin ? 'is-on' : '' ?>"><?= a_icon($s[3], 15) ?><span><?= e($s[2]) ?></span><?php if ($s[4]): ?><i><?= $s[4] ?></i><?php endif; ?></a><?php endforeach; ?>
  </div>
  <div class="ovl ovl--drawer ovl--left lx-drawer" id="lxDrawer" hidden><div class="ovl__bg" data-lx="close"></div><div class="ovl__panel" role="dialog" aria-label="Navigation"><div class="dnav">
    <div class="dnav__h"><span class="brand"><span class="brand__m"><img src="/assets/img/favicon-64.png" alt=""></span><span class="brand__w">Reside<em>Kenya</em></span></span><button class="ib" type="button" data-lx="close" aria-label="Close"><?= a_icon('x', 18) ?></button></div>
    <?php foreach (array_merge($__app, $__more) as $t): ?><a href="/admin/#/<?= $t[0] ?>" class="dnav__a<?= $t[0] === 'listings' && $__propTabs ? ' is-on' : '' ?>"><?= a_icon($t[2], 18) ?><span><?= e($t[1]) ?></span></a><?php endforeach; ?>
    <a href="/admin/#/chat" class="dnav__a"><?= a_icon('chat', 18) ?><span>Team chat</span></a>
    <div class="dnav__sep"></div><div class="dnav__g">Website</div>
    <?php foreach ($__site as $s): if ($s[0] === 'users' && !user_can('super_admin', 'admin')) continue; ?><a href="/admin/<?= $s[1] ?>" class="dnav__a<?= $s[0] === $activeAdmin ? ' is-on' : '' ?>"><?= a_icon($s[3], 18) ?><span><?= e($s[2]) ?></span><?php if ($s[4]): ?><i><?= $s[4] ?></i><?php endif; ?></a><?php endforeach; ?>
    <div class="dnav__sep"></div><a href="/" target="_blank" class="dnav__a"><?= a_icon('out', 18) ?><span>View website</span></a><a href="/admin/logout" class="dnav__a"><?= a_icon('x', 18) ?><span>Sign out</span></a>
  </div></div></div>
  <main class="admin-body" id="main">
    <header class="ph lx-ph"><h1><?= e($pageTitle) ?></h1><?php if (!empty($pageLead)): ?><span class="muted"><?= e($pageLead) ?></span><?php endif; ?></header>
    <?php if ($__flash = flash('success')): ?><div class="a-flash success"><?= a_icon('check', 16) ?><span><?= e($__flash) ?></span></div><?php endif; ?>
    <?php if ($__flash = flash('error')): ?><div class="a-flash error"><?= a_icon('alert', 16) ?><span><?= e($__flash) ?></span></div><?php endif; ?>
