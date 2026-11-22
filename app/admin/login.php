<?php
/** admin/login.php */
require __DIR__ . '/../includes/bootstrap.php';

if (is_logged_in()) redirect('admin/index.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $email = (string) input('email', '');
    $pass  = (string) input('password', '');
    if (auth_attempt($email, $pass)) {
        redirect('admin/index.php');
    }
    if (!flash('error')) flash('error', 'Invalid email or password.');
    redirect('admin/login.php');
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="theme-color" content="#16202c">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Reside Admin">
<meta name="format-detection" content="telephone=no">
<link rel="manifest" href="/admin/manifest.webmanifest">
<link rel="icon" href="/admin/icons/admin-32.png" sizes="32x32" type="image/png">
<link rel="icon" href="/admin/icons/admin-192.png" sizes="192x192" type="image/png">
<link rel="apple-touch-icon" href="/admin/icons/admin-180.png">
<title>Sign in — <?= e(SITE_NAME) ?> Admin</title>
<meta name="robots" content="noindex, nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('admin/assets/admin.css')) ?>">
</head>
<body class="admin">
<div class="login-wrap">
  <div class="login-card">
    <div class="lb">Reside<b>&nbsp;Ke</b></div>
    <p class="sub">Admin Control Panel</p>
    <?php if ($f = flash('error')): ?><div class="a-flash error"><?= e($f) ?></div><?php endif; ?>
    <form method="post" action="<?= e(url('admin/login.php')) ?>">
      <?= csrf_field() ?>
      <div class="a-field"><label>Email</label><input class="a-input" type="email" name="email" required autofocus></div>
      <div class="a-field"><label>Password</label><input class="a-input" type="password" name="password" required></div>
      <button class="a-btn gold" style="width:100%;justify-content:center" type="submit">Sign In</button>
    </form>
  </div>
</div>
</body>
</html>
