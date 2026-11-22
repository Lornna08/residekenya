<?php
/** policy.php — privacy, terms, cookies and listing disclaimer (edited in Admin → Website → Pages). */
require __DIR__ . '/includes/bootstrap.php';

$pages = ['privacy', 'terms', 'cookies', 'disclaimer'];
$p = (string) ($_GET['p'] ?? 'privacy');
if (!in_array($p, $pages, true)) { http_response_code(404); $p = 'privacy'; }

$title = c("{$p}_title");
$updated = c("{$p}_updated");
$seo = [
    'title'       => $title . ' — ' . SITE_NAME,
    'description' => c("{$p}_intro"),
    'canonical'   => url($p),
];
$activeNav = '';
include __DIR__ . '/templates/header.php';
?>
<section class="page-hero page-hero--slim">
  <div class="wrap">
    <p class="eyebrow">Legal</p>
    <h1 class="display"><?= e($title) ?></h1>
    <p><?= e(c("{$p}_intro")) ?></p>
  </div>
</section>
<div class="wrap section legal">
  <aside class="legal__nav" aria-label="Policies">
    <?php foreach ($pages as $k): ?>
      <a href="<?= e(url($k)) ?>"<?= $k === $p ? ' aria-current="page"' : '' ?>><?= e(c("{$k}_title")) ?></a>
    <?php endforeach; ?>
  </aside>
  <article class="legal__body">
    <?php if ($updated): ?><p class="legal__date">Last updated <?= e(date('j F Y', strtotime($updated) ?: time())) ?></p><?php endif; ?>
    <?= md(c("{$p}_body")) ?>
  </article>
</div>
<?php include __DIR__ . '/templates/footer.php'; ?>
