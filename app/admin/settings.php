<?php
/** admin/settings.php — site settings, contact info, socials, SEO defaults, SMTP. */
require __DIR__ . '/../includes/bootstrap.php';
require_role(['super_admin', 'admin']);

$defs = [
    'General' => [
        'site_name' => 'Site name',
        'site_tagline' => 'Tagline',
        'default_theme' => 'Default theme (light, dark, blue, or earth)',
        'contact_email' => 'Contact email',
        'contact_phone' => 'Contact phone',
        'whatsapp_number' => 'WhatsApp number (digits only)',
        'office_address' => 'Office address',
    ],
    'Social' => [
        'instagram' => 'Instagram handle (no @)',
        'tiktok' => 'TikTok handle (no @)',
        'facebook' => 'Facebook URL',
        'twitter' => 'X / Twitter handle',
    ],
    'SEO defaults' => [
        'meta_description' => 'Default meta description',
        'meta_keywords' => 'Default keywords',
    ],
    'Email (SMTP — used if you wire PHPMailer)' => [
        'smtp_from' => 'From email',
        'smtp_host' => 'SMTP host',
        'smtp_port' => 'SMTP port',
        'smtp_user' => 'SMTP username',
        'smtp_pass' => 'SMTP password',
    ],
    'About page stats' => [
        'about_stat1_value' => 'Stat 1 value (e.g. 300+)',
        'about_stat1_label' => 'Stat 1 label',
        'about_stat2_value' => 'Stat 2 value (e.g. 20+)',
        'about_stat2_label' => 'Stat 2 label',
        'about_stat3_value' => 'Stat 3 value (e.g. 20+)',
        'about_stat3_label' => 'Stat 3 label',
        'about_stat4_value' => 'Stat 4 value (e.g. 100%)',
        'about_stat4_label' => 'Stat 4 label',
    ],
    'Maps & embeds' => [
        'office_map_embed' => 'Contact page map embed (full <iframe> HTML)',
    ],
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
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

$pageTitle = 'Settings & SEO';
$activeAdmin = 'settings';
include __DIR__ . '/templates/layout-head.php';
?>
<form method="post">
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
            <label><?= e($label) ?> <span class="muted" style="font-size:.72rem">(<?= e($key) ?>)</span></label>
            <?php if ($isLong): ?>
              <textarea class="a-textarea" name="<?= e($key) ?>" style="min-height:70px"><?= e($val) ?></textarea>
            <?php else: ?>
              <input class="a-input" type="<?= $isSecret ? 'password' : 'text' ?>" name="<?= e($key) ?>" value="<?= e($val) ?>" <?= $isSecret ? 'autocomplete="new-password"' : '' ?>>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <div style="position:sticky;bottom:0;background:var(--a-bg);padding:1rem 0;border-top:1px solid var(--a-line)">
    <button class="a-btn gold" type="submit">Save Settings</button>
  </div>
</form>
<?php include __DIR__ . '/templates/layout-foot.php'; ?>
