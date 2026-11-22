<?php
/** admin/settings.php — site settings, contact info, socials, SEO defaults, SMTP. */
require __DIR__ . '/../includes/bootstrap.php';
require_role(['super_admin', 'admin']);

$defs = [
    'General' => [
        'site_name' => 'Site name',
        'site_tagline' => 'Tagline',
        'default_theme' => 'Default website theme',
    ],
    'SEO defaults' => [
        'meta_description' => 'Default meta description',
        'meta_keywords' => 'Default keywords',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    begin_site_save();
    foreach ($defs as $group) {
        foreach ($group as $key => $_label) {
            if (array_key_exists($key, $_POST)) {
                setting_save($key, trim((string) $_POST[$key]));
            }
        }
    }
    log_activity('update_settings', 'settings', null);
    flash('success', 'Settings saved.');
    redirect('admin/settings.php');
}

$previewUrl = '/';
$pageTitle = 'Website settings';
$pageLead = 'Name, tagline, default theme and search-engine text';
$activeAdmin = 'settings';
include __DIR__ . '/templates/layout-head.php';
?>
<div class="ed-note"><?= a_icon('info', 15) ?><span>Phone, email, WhatsApp, address and social links are in <a href="/admin/site-editor?p=details">Contact &amp; socials</a>. Page text and policies are in <a href="/admin/site-editor">Pages &amp; policies</a>.</span></div>
<form method="post" data-preview>
  <?= csrf_field() ?>
  <?php foreach ($defs as $group => $fields): ?>
    <div class="panel">
      <div class="panel-head"><h2><?= e($group) ?></h2></div>
      <div class="panel-body">
        <?php foreach ($fields as $key => $label):
          $val = setting($key, '');
          $isLong = in_array($key, ['meta_description', 'office_map_embed', 'meta_keywords'], true);
          $isSecret = $key === 'smtp_pass';
        ?>
          <div class="a-field">
            <label><?= e($label) ?></label>
            <?php if ($key === 'default_theme'): ?>
              <select class="a-select" name="default_theme"><?php foreach (['dark' => 'Luxury dark', 'light' => 'Light premium', 'blue' => 'Professional blue', 'earth' => 'Earth & nature'] as $tk => $tl): ?><option value="<?= $tk ?>"<?= ($val ?: 'dark') === $tk ? ' selected' : '' ?>><?= e($tl) ?></option><?php endforeach; ?></select>
              <small class="muted">Visitors can still switch theme; this is what they see first.</small>
            <?php elseif ($isLong): ?>
              <textarea class="a-textarea" name="<?= e($key) ?>" style="min-height:70px"><?= e($val) ?></textarea>
            <?php else: ?>
              <input class="a-input" type="<?= $isSecret ? 'password' : 'text' ?>" name="<?= e($key) ?>" value="<?= e($val) ?>" <?= $isSecret ? 'autocomplete="new-password"' : '' ?>>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <div class="ed-save"><span class="muted" data-pv-state>Changes show in the preview — they go live when you save.</span><button class="a-btn gold" type="submit"><?= a_icon('check', 15) ?> Save &amp; publish</button></div>
</form>
<?php include __DIR__ . '/templates/layout-foot.php'; ?>
