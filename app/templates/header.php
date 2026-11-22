<?php
/** Header partial. Set $seo before include for meta. */
$seo = $seo ?? [];
$nav = [
  'Home'       => '/',
  'Buy'        => 'listings.php?purpose=sale',
  'Rent'       => 'listings.php?purpose=rent',
  'Airbnb'     => 'listings.php?purpose=airbnb',
  'Commercial' => 'listings.php?purpose=commercial',
  'Land'       => 'listings.php?purpose=land',
  'About'      => 'about',
  'Contact'    => 'contact',
];
$activeNav = $activeNav ?? '';
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#0f1115">
<?= seo_head($seo) ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400;1,500&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('assets/css/main.css')) ?>?v=<?= ASSET_V ?>">
<link rel="icon" href="<?= e(url('favicon.ico')) ?>" sizes="48x48">
<link rel="icon" href="<?= e(url('assets/img/favicon-32.png')) ?>" sizes="32x32" type="image/png">
<link rel="icon" href="<?= e(url('assets/img/favicon-192.png')) ?>" sizes="192x192" type="image/png">
<link rel="apple-touch-icon" href="<?= e(url('assets/img/favicon-180.png')) ?>">
<link rel="manifest" href="<?= e(url('site.webmanifest')) ?>">
<script>window.RESIDE_BASE = <?= json_encode(rtrim(BASE_URL, '/')) ?>;</script>
<script>(function(){try{var d=<?= json_encode(setting('default_theme', 'dark')) ?>;var t=localStorage.getItem('reside_theme')||d;if(t&&t!=='light'){document.documentElement.setAttribute('data-theme',t);}}catch(e){}})();</script>
<?= $extraHead ?? '' ?>
<noscript><style>.reveal{opacity:1!important;transform:none!important}</style></noscript>
</head>
<body>
<a class="skip-link" href="#main">Skip to content</a>
<header class="site-header" id="siteHeader">
  <div class="wrap nav">
    <a class="brand" href="<?= e(url('/')) ?>" aria-label="Reside Kenya home">
      <img class="brand-mark" src="/assets/img/mark.png" alt="" width="37" height="40"><span class="brand-word">Reside <b>Kenya</b></span>
    </a>
    <nav class="nav-links" aria-label="Primary">
      <?php foreach ($nav as $label => $href): ?>
        <a href="<?= e(url($href)) ?>"<?= $activeNav === $label ? ' class="active"' : '' ?>><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="nav-cta">
      <div class="theme-switch" id="themeSwitch">
        <button class="theme-btn" id="themeBtn" aria-label="Change theme" aria-expanded="false" title="Theme">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="12" r="9"/><path d="M12 3a9 9 0 0 0 0 18 4.5 4.5 0 0 1 0-9 4.5 4.5 0 0 0 0-9z"/></svg>
        </button>
        <div class="theme-menu" id="themeMenu" role="menu">
          <button data-theme-choice="light" role="menuitem"><span class="sw" style="background:#2d5a4a"></span> Light Premium</button>
          <button data-theme-choice="dark" role="menuitem"><span class="sw" style="background:#0f1115;border-color:#4e9f7a"></span> Luxury Dark</button>
          <button data-theme-choice="blue" role="menuitem"><span class="sw" style="background:#1f4e79"></span> Professional Blue</button>
          <button data-theme-choice="earth" role="menuitem"><span class="sw" style="background:#5f7150"></span> Earth &amp; Nature</button>
        </div>
      </div>
      <a class="btn btn--ghost btn--sm" href="<?= e(url('list-property.php')) ?>">List Property</a>
      <button class="nav-toggle" aria-label="Menu" aria-expanded="false" id="navToggle">
        <span></span><span></span><span></span>
      </button>
    </div>
  </div>
  <div class="mobile-menu" id="mobileMenu">
    <?php foreach ($nav as $label => $href): ?>
      <a href="<?= e(url($href)) ?>"><?= e($label) ?></a>
    <?php endforeach; ?>
    <a href="<?= e(url('list-property.php')) ?>">List Your Property</a>
    <div class="mobile-themes">
      <span class="mt-label">Theme</span>
      <div class="mt-row">
        <button data-theme-choice="light" title="Light Premium"><span class="sw" style="background:#2d5a4a"></span></button>
        <button data-theme-choice="dark" title="Luxury Dark"><span class="sw" style="background:#0f1115;border-color:#4e9f7a"></span></button>
        <button data-theme-choice="blue" title="Professional Blue"><span class="sw" style="background:#1f4e79"></span></button>
        <button data-theme-choice="earth" title="Earth &amp; Nature"><span class="sw" style="background:#5f7150"></span></button>
      </div>
    </div>
  </div>
</header>
<main id="main">
