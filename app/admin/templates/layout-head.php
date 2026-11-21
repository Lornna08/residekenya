<?php
/**
 * Admin layout header. Include after requiring bootstrap + require_login().
 * Expects: $pageTitle (string), $activeAdmin (string key).
 */
$u = current_user();
$pageTitle = $pageTitle ?? 'Dashboard';
$activeAdmin = $activeAdmin ?? '';

// Notification counts for sidebar badges
$unreadInquiries = (int) (Database::row("SELECT COUNT(*) n FROM inquiries WHERE is_read = 0")['n'] ?? 0);
$pendingBookings = (int) (Database::row("SELECT COUNT(*) n FROM bookings WHERE status = 'pending'")['n'] ?? 0);
$newSubmissions  = (int) (Database::row("SELECT COUNT(*) n FROM submissions WHERE status = 'new'")['n'] ?? 0);

function a_nav(string $key, string $active, string $href, string $label, int $badge = 0): string
{
    $cls = $key === $active ? ' class="active"' : '';
    $b = $badge > 0 ? '<span class="badge-n">' . $badge . '</span>' : '';
    return '<a href="' . e(url('admin/' . $href)) . '"' . $cls . '>' . e($label) . $b . '</a>';
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> — <?= e(SITE_NAME) ?> Admin</title>
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('admin/assets/admin.css')) ?>">
</head>
<body class="admin">
<div class="admin-shell">
  <aside class="admin-side" id="adminSide">
    <div class="admin-brand">Reside<b>&nbsp;Ke</b></div>
    <nav class="admin-nav">
      <div class="grp">Overview</div>
      <?= a_nav('dashboard', $activeAdmin, 'index.php', 'Dashboard') ?>
      <div class="grp">Catalogue</div>
      <?= a_nav('properties', $activeAdmin, 'properties.php', 'Properties') ?>
      <?= a_nav('property-edit', $activeAdmin, 'property-edit.php', 'Add Property') ?>
      <?= a_nav('categories', $activeAdmin, 'categories.php', 'Categories') ?>
      <?= a_nav('locations', $activeAdmin, 'locations.php', 'Counties & Towns') ?>
      <?= a_nav('amenities', $activeAdmin, 'amenities.php', 'Amenities') ?>
      <?= a_nav('agents', $activeAdmin, 'agents.php', 'Agents') ?>
      <?= a_nav('seed', $activeAdmin, 'seed-defaults.php', 'Load KE Defaults') ?>
      <div class="grp">Engagement</div>
      <?= a_nav('inquiries', $activeAdmin, 'inquiries.php', 'Inquiries', $unreadInquiries) ?>
      <?= a_nav('bookings', $activeAdmin, 'bookings.php', 'Bookings', $pendingBookings) ?>
      <?= a_nav('submissions', $activeAdmin, 'submissions.php', 'Submissions', $newSubmissions) ?>
      <?= a_nav('testimonials', $activeAdmin, 'testimonials.php', 'Testimonials') ?>
      <?= a_nav('subscribers', $activeAdmin, 'subscribers.php', 'Subscribers') ?>
      <div class="grp">System</div>
      <?= a_nav('homepage', $activeAdmin, 'homepage.php', 'Homepage Editor') ?>
      <?= a_nav('import', $activeAdmin, 'import-export.php', 'Import / Export') ?>
      <?= a_nav('settings', $activeAdmin, 'settings.php', 'Settings & SEO') ?>
      <?php if (user_can('super_admin', 'admin')): ?><?= a_nav('users', $activeAdmin, 'users.php', 'Users') ?><?php endif; ?>
      <?= a_nav('activity', $activeAdmin, 'activity.php', 'Activity Log') ?>
    </nav>
    <div class="admin-side-foot">
      <a href="<?= e(url('/')) ?>" target="_blank" rel="noopener">View site →</a>
    </div>
  </aside>

  <div class="admin-main">
    <div class="admin-top">
      <div style="display:flex;align-items:center;gap:.8rem">
        <button class="a-btn ghost sm admin-toggle" id="adminToggle" aria-label="Menu">☰</button>
        <h1><?= e($pageTitle) ?></h1>
      </div>
      <div class="who">
        <span><?= e($u['name']) ?> · <span class="pill info"><?= e(str_replace('_', ' ', $u['role'])) ?></span></span>
        <a class="a-btn sm" href="<?= e(url('admin/logout.php')) ?>">Sign out</a>
      </div>
    </div>
    <div class="admin-body">
      <?php if ($__flash = flash('success')): ?><div class="a-flash success"><?= e($__flash) ?></div><?php endif; ?>
      <?php if ($__flash = flash('error')): ?><div class="a-flash error"><?= e($__flash) ?></div><?php endif; ?>
