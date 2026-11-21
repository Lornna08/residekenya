<?php
/** sitemap.php — dynamic XML sitemap. */
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=UTF-8');

$base = rtrim(BASE_URL, '/');
$static = ['', 'listings.php', 'about.php', 'contact.php', 'list-property.php'];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($static as $path) {
    $loc = $base . '/' . $path;
    echo "  <url><loc>" . e($loc) . "</loc><changefreq>weekly</changefreq><priority>" . ($path === '' ? '1.0' : '0.7') . "</priority></url>\n";
}

$props = Database::all("SELECT slug, updated_at, created_at FROM properties WHERE status IN ('available','under_offer') ORDER BY created_at DESC LIMIT 5000");
foreach ($props as $p) {
    $loc = $base . '/property.php?slug=' . rawurlencode($p['slug']);
    $lastmod = date('Y-m-d', strtotime($p['updated_at'] ?: $p['created_at']));
    echo "  <url><loc>" . e($loc) . "</loc><lastmod>" . e($lastmod) . "</lastmod><changefreq>weekly</changefreq><priority>0.8</priority></url>\n";
}

// Purpose landing pages
foreach (['sale', 'rent', 'airbnb', 'commercial', 'land'] as $purpose) {
    echo "  <url><loc>" . e($base . '/listings.php?purpose=' . $purpose) . "</loc><changefreq>daily</changefreq><priority>0.6</priority></url>\n";
}

echo '</urlset>';
