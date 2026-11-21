<?php
/** admin/seed-defaults.php — one-click load of Kenyan categories & towns (no phpMyAdmin needed). */
require __DIR__ . '/../includes/bootstrap.php';
require_role(['super_admin', 'admin']);

$CATEGORIES = [
    'Apartment', 'Studio Apartment', 'Serviced Apartment', 'Penthouse',
    'Maisonette', 'Villa', 'Luxury Villa', 'Bungalow', 'Cottage',
    'Townhouse', 'Duplex', 'Mansion', 'Flat', 'Semi-Detached House',
    'Detached House', 'Holiday Home', 'Airbnb', 'Commercial', 'Office Space',
    'Retail Space', 'Warehouse', 'Godown', 'Shop', 'Land',
    'Residential Plot', 'Commercial Plot', 'Agricultural Land', 'Beachfront Property',
    'Gated Community', 'Farmhouse',
];

// Common towns/areas grouped by county name (only added if the county exists)
$TOWNS = [
    'Nairobi'   => ['Westlands', 'Kilimani', 'Kileleshwa', 'Lavington', 'Karen', 'Runda', 'Muthaiga', 'Parklands', 'South B', 'South C', 'Langata', 'Embakasi', 'Ruaka', 'Kasarani', 'Roysambu', 'Donholm', 'Upperhill', 'Ngong Road', 'Riverside', 'Spring Valley'],
    'Kiambu'    => ['Thika', 'Ruiru', 'Juja', 'Kikuyu', 'Limuru', 'Kiambu Town', 'Karuri', 'Githunguri', 'Gatundu', 'Tigoni', 'Ngong'],
    'Mombasa'   => ['Nyali', 'Bamburi', 'Kizingo', 'Tudor', 'Likoni', 'Mtwapa', 'Shanzu', 'Kisauni', 'Diani Road'],
    'Nakuru'    => ['Nakuru Town', 'Naivasha', 'Gilgil', 'Molo', 'Njoro', 'Bahati'],
    'Kisumu'    => ['Kisumu Central', 'Milimani', 'Mamboleo', 'Nyalenda', 'Kondele'],
    'Machakos'  => ['Machakos Town', 'Athi River', 'Mlolongo', 'Syokimau', 'Kangundo', 'Mavoko'],
    'Kajiado'   => ['Kitengela', 'Ngong', 'Ongata Rongai', 'Kiserian', 'Isinya', 'Namanga'],
    'Uasin Gishu' => ['Eldoret', 'Turbo', 'Burnt Forest'],
    'Nyeri'     => ['Nyeri Town', 'Karatina', 'Othaya'],
    'Kilifi'    => ['Kilifi Town', 'Malindi', 'Watamu', 'Mtwapa', 'Mariakani'],
    'Kwale'     => ['Diani', 'Ukunda', 'Msambweni', 'Kwale Town'],
    'Meru'      => ['Meru Town', 'Maua', 'Nkubu'],
    'Laikipia'  => ['Nanyuki', 'Nyahururu', 'Rumuruti'],
];

$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();
    $addedCats = 0; $addedTowns = 0;

    if (input('do_categories')) {
        $order = 1;
        foreach ($CATEGORIES as $name) {
            $exists = Database::row("SELECT id FROM categories WHERE name = ?", [$name]);
            if (!$exists) {
                Database::run("INSERT INTO categories (name, slug, sort_order, is_active) VALUES (?,?,?,1)",
                    [$name, unique_slug('categories', $name), $order]);
                $addedCats++;
            }
            $order++;
        }
    }

    if (input('do_towns')) {
        foreach ($TOWNS as $countyName => $towns) {
            $county = Database::row("SELECT id FROM counties WHERE name = ?", [$countyName]);
            if (!$county) continue;
            foreach ($towns as $town) {
                $exists = Database::row("SELECT id FROM towns WHERE county_id = ? AND name = ?", [$county['id'], $town]);
                if (!$exists) {
                    Database::run("INSERT INTO towns (county_id, name, slug) VALUES (?,?,?)",
                        [$county['id'], $town, unique_slug('towns', $town)]);
                    $addedTowns++;
                }
            }
        }
    }

    log_activity('seed_defaults', 'settings', null);
    flash('success', "Done — added {$addedCats} categories and {$addedTowns} towns. Existing entries were left untouched.");
    redirect('admin/seed-defaults.php');
}

$pageTitle = 'Load Kenya Defaults';
$activeAdmin = 'seed';
include __DIR__ . '/templates/layout-head.php';
?>
<div class="panel">
  <div class="panel-head"><h2>Quick-load Kenyan defaults</h2></div>
  <div class="panel-body">
    <p class="muted" style="font-size:.92rem;line-height:1.65;max-width:640px">
      This adds a ready-made set of Kenyan property <strong>categories</strong> and common <strong>towns/areas</strong> for the major counties, so you don't have to type them all in.
      It only <em>adds</em> what's missing — nothing you've already created is changed or removed. Safe to run more than once.
    </p>

    <form method="post" style="margin-top:1.4rem">
      <?= csrf_field() ?>
      <label class="a-check"><input type="checkbox" name="do_categories" value="1" checked> Load <?= count($CATEGORIES) ?> property categories (Maisonette, Villa, Bungalow, Townhouse, Cottage, Penthouse, Land types, Commercial…)</label>
      <label class="a-check"><input type="checkbox" name="do_towns" value="1" checked> Load common towns/areas for major counties (Nairobi, Kiambu, Mombasa, Nakuru, Kisumu, Machakos, Kajiado…)</label>
      <div style="margin-top:1.2rem">
        <button class="a-btn gold" type="submit">Load Defaults Now</button>
      </div>
    </form>

    <div style="margin-top:1.6rem;border-top:1px solid var(--a-line);padding-top:1.2rem">
      <p class="muted" style="font-size:.84rem">Manage them anytime under <a href="<?= e(url('admin/categories.php')) ?>">Categories</a> and <a href="<?= e(url('admin/locations.php')) ?>">Counties &amp; Towns</a>.</p>
    </div>
  </div>
</div>
<?php include __DIR__ . '/templates/layout-foot.php'; ?>
