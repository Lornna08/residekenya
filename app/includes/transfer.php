<?php
/**
 * Import / export of listings and workspace data.
 *
 * One listing format is used everywhere (CSV columns = JSON keys), so a file
 * exported here can be edited in Excel/Sheets and imported back:
 *   ref, title, purpose, type, status, price, rent_period, county, area,
 *   address, bedrooms, bathrooms, floor_area_sqm, plot, floors, parking,
 *   featured, luxury, amenities (a|b|c), images (url|url), description,
 *   youtube_url, latitude, longitude, owner_name, owner_contact, source_url,
 *   website_link, listed_at
 */

const TX_COLS = ['ref', 'title', 'purpose', 'type', 'status', 'price', 'rent_period', 'county', 'area', 'address',
    'bedrooms', 'bathrooms', 'floor_area_sqm', 'plot', 'floors', 'parking', 'featured', 'luxury', 'amenities', 'images',
    'description', 'youtube_url', 'latitude', 'longitude', 'owner_name', 'owner_contact', 'source_url', 'website_link', 'listed_at'];

/** Every listing as a flat row. */
function tx_listing_rows(): array
{
    $extra = [];
    foreach (Database::all("SELECT id, latitude, longitude, youtube_url FROM properties") as $r) $extra[(int) $r['id']] = $r;
    $rows = [];
    foreach (crm_properties() as $p) {
        if (!empty($p['archived'])) continue;
        $x = $extra[$p['dbId']] ?? [];
        $imgs = array_values(array_filter($p['images'], fn($u) => !str_contains($u, 'placeholder')));
        $rows[] = [
            'ref' => $p['ref'], 'title' => $p['title'], 'purpose' => $p['purpose'], 'type' => $p['type'], 'status' => $p['status'],
            'price' => $p['price'] ?: '', 'rent_period' => $p['category'] === 'rent' ? $p['rentPeriod'] : '',
            'county' => $p['city'], 'area' => $p['area'] !== $p['city'] ? $p['area'] : '', 'address' => $p['address'],
            'bedrooms' => $p['beds'] ?: '', 'bathrooms' => $p['baths'] ?: '', 'floor_area_sqm' => $p['size'] ?: '', 'plot' => $p['plot'],
            'floors' => $p['floors'] ?: '', 'parking' => $p['parking'] ?: '', 'featured' => $p['featured'] ? 1 : 0, 'luxury' => $p['luxury'] ? 1 : 0,
            'amenities' => implode('|', $p['amenities']), 'images' => implode('|', $imgs), 'description' => $p['description'],
            'youtube_url' => $p['youtube'], 'latitude' => $x['latitude'] ?? '', 'longitude' => $x['longitude'] ?? '',
            'owner_name' => $p['ownerName'], 'owner_contact' => $p['ownerContact'], 'source_url' => $p['sourceUrl'],
            'website_link' => $p['url'], 'listed_at' => substr((string) $p['listedAt'], 0, 10),
        ];
    }
    return $rows;
}

/** Send rows as a CSV download (UTF-8 with BOM so Excel shows shillings and accents correctly). */
function tx_csv_download(string $filename, array $cols, array $rows): void
{
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $cols);
    foreach ($rows as $r) fputcsv($out, array_map(fn($c) => is_array($r[$c] ?? null) ? implode('|', $r[$c]) : (string) ($r[$c] ?? ''), $cols));
    fclose($out);
    exit;
}

function tx_json_download(string $filename, $data): void
{
    header('Content-Type: application/json; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/** Normalise a heading from someone's spreadsheet to one of our keys. */
function tx_key(string $h): string
{
    $k = strtolower(trim(preg_replace('/[^a-z0-9]+/i', '_', $h), '_'));
    $alias = [
        'ref_code' => 'ref', 'reference' => 'ref', 'name' => 'title', 'listing' => 'title', 'property' => 'title',
        'category' => 'type', 'property_type' => 'type', 'listing_type' => 'purpose', 'for' => 'purpose',
        'asking_price' => 'price', 'price_kes' => 'price', 'rent' => 'price', 'sale_price' => 'price', 'rent_price' => 'price',
        'location' => 'area', 'town' => 'area', 'neighbourhood' => 'area', 'neighborhood' => 'area', 'estate' => 'address', 'city' => 'county',
        'beds' => 'bedrooms', 'baths' => 'bathrooms', 'size' => 'floor_area_sqm', 'area_sqm' => 'floor_area_sqm', 'size_m2' => 'floor_area_sqm',
        'land_size' => 'plot', 'land_size_sqm' => 'plot', 'plot_size' => 'plot', 'parking_spaces' => 'parking',
        'is_featured' => 'featured', 'is_luxury' => 'luxury', 'features' => 'amenities', 'photos' => 'images', 'image_urls' => 'images',
        'photo_urls' => 'images', 'pictures' => 'images', 'video' => 'youtube_url', 'youtube' => 'youtube_url', 'lat' => 'latitude',
        'lng' => 'longitude', 'lon' => 'longitude', 'owner' => 'owner_name', 'owner_phone' => 'owner_contact', 'source' => 'source_url',
        'link' => 'source_url', 'url' => 'source_url', 'rent_period' => 'rent_period', 'period' => 'rent_period',
    ];
    return $alias[$k] ?? $k;
}

/** Download an image from the web and keep our own copy. Returns the stored path or URL. */
function tx_fetch_image(string $url): ?string
{
    if (!preg_match('#^https?://#i', $url)) return null;
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4, CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_USERAGENT => 'Mozilla/5.0 (ResideKenya importer)', CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_MAXFILESIZE => 15 * 1024 * 1024,
    ]);
    $bytes = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if (!is_string($bytes) || $code >= 300 || strlen($bytes) < 500) return null;
    $mime = (new finfo(FILEINFO_MIME_TYPE))->buffer($bytes);
    $ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime] ?? null;
    if (!$ext) return null;
    $rel = 'uploads/properties/' . bin2hex(random_bytes(16)) . '.' . $ext;
    if (BLOB_TOKEN !== '') return blob_put($rel, $bytes, $mime);
    $dir = UPLOAD_PATH . '/properties';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    return file_put_contents(UPLOAD_PATH . '/properties/' . basename($rel), $bytes) ? $rel : null;
}

function tx_num($v): ?float
{
    $s = preg_replace('/[^0-9.\-]/', '', str_replace(',', '', (string) $v));
    if ($s === '' || !is_numeric($s)) return null;
    $n = (float) $s;
    if (preg_match('/(\d)\s*(m|million)\b/i', (string) $v)) $n *= 1e6;
    elseif (preg_match('/(\d)\s*k\b/i', (string) $v)) $n *= 1e3;
    return $n;
}

/**
 * Import one listing row. Matches an existing listing by ref (then by exact title),
 * re-hosts any outside photos, and saves through the same code path as the app.
 * Returns ['ok' => bool, 'action' => created|updated|skipped, 'id', 'title', 'photos', 'photoErrors', 'error'].
 */
function tx_import_row(array $in, int $uid, bool $rehost = true): array
{
    $r = [];
    foreach ($in as $k => $v) $r[tx_key((string) $k)] = is_string($v) ? trim($v) : $v;
    $title = (string) ($r['title'] ?? '');
    if (mb_strlen($title) < 3) return ['ok' => false, 'action' => 'skipped', 'error' => 'No title'];

    $existing = null;
    if (!empty($r['ref'])) $existing = Database::row("SELECT id FROM properties WHERE ref_code = ?", [$r['ref']]);
    if (!$existing) $existing = Database::row("SELECT id FROM properties WHERE LOWER(title) = LOWER(?) AND status <> 'archived' LIMIT 1", [$title]);
    $cur = $existing ? (crm_properties((int) $existing['id'])[0] ?? null) : null;

    $purpose = strtolower((string) ($r['purpose'] ?? ''));
    $purpose = match (true) {
        str_contains($purpose, 'airbnb') || str_contains($purpose, 'short') || str_contains($purpose, 'holiday') => 'airbnb',
        str_contains($purpose, 'rent') || str_contains($purpose, 'let') || str_contains($purpose, 'lease') => 'rent',
        str_contains($purpose, 'commercial') || str_contains($purpose, 'office') => 'commercial',
        str_contains($purpose, 'land') || str_contains($purpose, 'plot') => 'land',
        str_contains($purpose, 'sale') || str_contains($purpose, 'sell') || str_contains($purpose, 'buy') => 'sale',
        default => $cur['purpose'] ?? 'sale',
    };
    $statusIn = strtolower((string) ($r['status'] ?? ''));
    $status = match (true) {
        $statusIn === '' => $cur['status'] ?? 'active',
        in_array($statusIn, ['active', 'available', 'published', 'live'], true) => 'active',
        str_contains($statusIn, 'offer') => 'under_offer',
        in_array($statusIn, ['sold'], true) => 'sold',
        in_array($statusIn, ['let', 'rented', 'leased'], true) => 'let',
        in_array($statusIn, ['withdrawn', 'archived', 'hidden'], true) => 'withdrawn',
        default => 'draft',
    };

    // Photos: keep ours, copy everything else into our storage.
    $photos = []; $photoErrors = 0;
    $list = $r['images'] ?? [];
    if (is_string($list)) $list = preg_split('/[|\n;]+|,\s*(?=https?:)/', $list) ?: [];
    foreach ((array) $list as $u) {
        $u = trim((string) $u); if ($u === '') continue;
        if (crm_store_path($u)) { $photos[] = $u; continue; }
        if (!$rehost) { $photoErrors++; continue; }
        $saved = tx_fetch_image($u);
        if ($saved) $photos[] = asset_url($saved); else $photoErrors++;
        if (count($photos) >= 40) break;
    }

    $amen = $r['amenities'] ?? null;
    if (is_string($amen)) $amen = array_values(array_filter(array_map('trim', preg_split('/[|;,]+/', $amen) ?: [])));

    $p = [
        'id' => $cur['id'] ?? null, 'title' => $title, 'purpose' => $purpose, 'category' => in_array($purpose, ['rent', 'airbnb'], true) ? 'rent' : 'sale',
        'type' => $r['type'] ?? ($cur['type'] ?? 'Property'), 'status' => $status,
        'price' => tx_num($r['price'] ?? '') ?? ($cur['price'] ?? 0),
        'rentPeriod' => strtolower((string) ($r['rent_period'] ?? '')) ?: ($cur['rentPeriod'] ?? ($purpose === 'airbnb' ? 'night' : 'month')),
        'city' => $r['county'] ?? ($cur['city'] ?? ''), 'area' => ($r['area'] ?? '') !== '' ? $r['area'] : ($cur['area'] ?? ($r['county'] ?? '')),
        'address' => $r['address'] ?? ($cur['address'] ?? ''),
        'beds' => isset($r['bedrooms']) && $r['bedrooms'] !== '' ? (int) tx_num($r['bedrooms']) : ($cur['beds'] ?? ''),
        'baths' => isset($r['bathrooms']) && $r['bathrooms'] !== '' ? (int) tx_num($r['bathrooms']) : ($cur['baths'] ?? ''),
        'size' => isset($r['floor_area_sqm']) && $r['floor_area_sqm'] !== '' ? tx_num($r['floor_area_sqm']) : ($cur['size'] ?? ''),
        'plot' => $r['plot'] ?? ($cur['plot'] ?? ''),
        'floors' => isset($r['floors']) && $r['floors'] !== '' ? (int) tx_num($r['floors']) : ($cur['floors'] ?? ''),
        'parking' => isset($r['parking']) && $r['parking'] !== '' ? (int) tx_num($r['parking']) : ($cur['parking'] ?? ''),
        'featured' => isset($r['featured']) && $r['featured'] !== '' ? in_array(strtolower((string) $r['featured']), ['1', 'yes', 'true', 'y'], true) : ($cur['featured'] ?? false),
        'luxury' => isset($r['luxury']) && $r['luxury'] !== '' ? in_array(strtolower((string) $r['luxury']), ['1', 'yes', 'true', 'y'], true) : ($cur['luxury'] ?? false),
        'description' => (string) (($r['description'] ?? '') !== '' ? $r['description'] : ($cur['description'] ?? '')),
        'agentId' => $cur['agentId'] ?? ('u' . $uid),
    ];
    if ($photos) $p['images'] = $photos;
    if (is_array($amen) && $amen) $p['amenities'] = $amen;
    if (array_key_exists('owner_name', $r) || array_key_exists('owner_contact', $r)) { $p['ownerName'] = $r['owner_name'] ?? ''; $p['ownerContact'] = $r['owner_contact'] ?? ''; }
    if (!empty($r['source_url'])) $p['sourceUrl'] = $r['source_url'];
    if ($cur) foreach (['minPrice', 'estimate', 'notes', 'ownerId'] as $k) if (isset($cur[$k])) $p[$k] = $cur[$k];
    if ($cur && isset($p['price']) && ($cur['estimate'] ?? 0) == ($cur['price'] ?? 0)) $p['estimate'] = $p['price'];

    $res = crm_save_property($p, $uid);
    $dbId = (int) preg_replace('/\D/', '', (string) ($res['id'] ?? ''));
    $set = []; $vals = [];
    foreach (['youtube_url' => 'youtube_url', 'latitude' => 'latitude', 'longitude' => 'longitude'] as $k => $col) {
        if (!isset($r[$k]) || $r[$k] === '') continue;
        $set[] = "$col = ?"; $vals[] = $k === 'youtube_url' ? (filter_var($r[$k], FILTER_VALIDATE_URL) ?: null) : tx_num($r[$k]);
    }
    if ($set && $dbId) Database::run("UPDATE properties SET " . implode(', ', $set) . " WHERE id = ?", array_merge($vals, [$dbId]));
    return ['ok' => true, 'action' => $cur ? 'updated' : 'created', 'id' => $dbId, 'ref' => $res['ref'] ?? '', 'url' => $res['url'] ?? '', 'title' => $title, 'photos' => count($photos), 'photoErrors' => $photoErrors];
}
