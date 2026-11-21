<?php
/**
 * SEO helpers — meta tags, OpenGraph, Twitter cards, JSON-LD schema, breadcrumbs.
 */

function seo_head(array $o = []): string
{
    $title = $o['title'] ?? (setting('site_name', SITE_NAME) . ' — ' . setting('site_tagline'));
    $desc  = $o['description'] ?? setting('meta_description');
    $image = $o['image'] ?? url('assets/img/og-default.jpg');
    $canonical = $o['canonical'] ?? current_url();
    $type  = $o['type'] ?? 'website';

    $h  = '<title>' . e($title) . "</title>\n";
    $h .= '<meta name="description" content="' . e($desc) . "\">\n";
    $h .= '<link rel="canonical" href="' . e($canonical) . "\">\n";
    if (!empty($o['keywords'])) {
        $h .= '<meta name="keywords" content="' . e($o['keywords']) . "\">\n";
    }
    // OpenGraph
    $h .= '<meta property="og:title" content="' . e($title) . "\">\n";
    $h .= '<meta property="og:description" content="' . e($desc) . "\">\n";
    $h .= '<meta property="og:type" content="' . e($type) . "\">\n";
    $h .= '<meta property="og:url" content="' . e($canonical) . "\">\n";
    $h .= '<meta property="og:image" content="' . e($image) . "\">\n";
    $h .= '<meta property="og:site_name" content="' . e(setting('site_name', SITE_NAME)) . "\">\n";
    // Twitter
    $h .= '<meta name="twitter:card" content="summary_large_image">' . "\n";
    $h .= '<meta name="twitter:title" content="' . e($title) . "\">\n";
    $h .= '<meta name="twitter:description" content="' . e($desc) . "\">\n";
    $h .= '<meta name="twitter:image" content="' . e($image) . "\">\n";
    return $h;
}

function current_url(): string
{
    $uri = $_SERVER['REQUEST_URI'] ?? '/';
    return rtrim(BASE_URL, '/') . $uri;
}

/** JSON-LD schema for a single property listing. */
function schema_property(array $p, array $images = []): string
{
    $imgUrls = array_map(fn($i) => asset_url($i['file_path']), $images);
    if (!$imgUrls && !empty($p['featured_image'])) $imgUrls = [asset_url($p['featured_image'])];

    $data = [
        '@context' => 'https://schema.org',
        '@type'    => 'Residence',
        'name'     => $p['title'],
        'description' => mb_substr(strip_tags($p['description'] ?? ''), 0, 300),
        'url'      => url('property/' . $p['slug']),
        'image'    => $imgUrls,
    ];
    if (!empty($p['county_name']) || !empty($p['town_name'])) {
        $data['address'] = array_filter([
            '@type' => 'PostalAddress',
            'addressLocality' => $p['town_name'] ?? null,
            'addressRegion'   => $p['county_name'] ?? null,
            'addressCountry'  => 'KE',
        ]);
    }
    if (!empty($p['latitude']) && !empty($p['longitude'])) {
        $data['geo'] = [
            '@type' => 'GeoCoordinates',
            'latitude' => (float)$p['latitude'],
            'longitude' => (float)$p['longitude'],
        ];
    }
    if (!empty($p['price'])) {
        $data['offers'] = [
            '@type' => 'Offer',
            'price' => (string)$p['price'],
            'priceCurrency' => $p['currency'] ?? 'KES',
            'availability' => 'https://schema.org/InStock',
        ];
    }
    if (!empty($p['bedrooms']))  $data['numberOfRooms'] = (int)$p['bedrooms'];
    if (!empty($p['area_sqm'])) {
        $data['floorSize'] = ['@type' => 'QuantitativeValue', 'value' => (float)$p['area_sqm'], 'unitCode' => 'MTK'];
    }
    return '<script type="application/ld+json">' . json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . '</script>';
}

/** Breadcrumb: array of ['label'=>, 'url'=>] ; last has no url. */
function breadcrumbs(array $crumbs): string
{
    $items = [];
    $html  = '<nav class="breadcrumbs" aria-label="Breadcrumb"><ol>';
    foreach ($crumbs as $i => $c) {
        $pos = $i + 1;
        if (!empty($c['url'])) {
            $html .= '<li><a href="' . e($c['url']) . '">' . e($c['label']) . '</a></li>';
            $items[] = ['@type'=>'ListItem','position'=>$pos,'name'=>$c['label'],'item'=>$c['url']];
        } else {
            $html .= '<li aria-current="page">' . e($c['label']) . '</li>';
            $items[] = ['@type'=>'ListItem','position'=>$pos,'name'=>$c['label']];
        }
    }
    $html .= '</ol></nav>';
    $ld = ['@context'=>'https://schema.org','@type'=>'BreadcrumbList','itemListElement'=>$items];
    $html .= '<script type="application/ld+json">' . json_encode($ld, JSON_UNESCAPED_SLASHES) . '</script>';
    return $html;
}
